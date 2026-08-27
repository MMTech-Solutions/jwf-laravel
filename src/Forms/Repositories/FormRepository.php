<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms\Repositories;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use JsonException;
use Mmt\Jwf\Forms\Domain\FormDocument;
use Mmt\Jwf\Forms\Domain\FormState;
use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Forms\Domain\Input\Option;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Forms\Domain\Node\Node;
use Mmt\Jwf\Shared\Domain\Attributes;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersionReference;
use Mmt\JwfLaravel\Exceptions\FormHasSubmissionsException;
use Mmt\JwfLaravel\Exceptions\ImmutableVersionException;
use Mmt\JwfLaravel\Exceptions\JwfLaravelException;
use Mmt\JwfLaravel\Forms\Data\StoredFormVersion;
use RuntimeException;
use stdClass;

final readonly class FormRepository
{
    public function __construct(private DatabaseManager $database)
    {
    }

    public function create(string $name, FormDocument $document): StoredFormVersion
    {
        if ($document->state !== FormState::Draft) {
            throw new JwfLaravelException('A new form version must be a draft.');
        }

        return $this->database->connection()->transaction(function () use ($name, $document): StoredFormVersion {
            $templateId = (string) Str::uuid();
            $now = now();
            $this->database->table('jwf_form_templates')->insert([
                'id' => $templateId,
                'name' => $name,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $this->insertVersion($templateId, 1, $document);

            return new StoredFormVersion($templateId, 1, $document);
        });
    }

    public function saveDraft(FormDocument $document): StoredFormVersion
    {
        $record = $this->versionRecord($document->id->toString());
        if ($record->state !== FormState::Draft->value || $document->state !== FormState::Draft) {
            throw new ImmutableVersionException('Only draft form versions can be edited.');
        }

        $this->database->connection()->transaction(function () use ($record, $document): void {
            $recordId = $this->string($record->id);
            $this->database->table('jwf_form_nodes')->where('form_version_id', $recordId)->delete();
            $this->insertNode($recordId, $document->root, null);
            $this->database->table('jwf_form_versions')->where('id', $recordId)->update(['updated_at' => now()]);
        });

        return new StoredFormVersion(
            $this->string($record->template_id),
            $this->integer($record->number),
            $document,
        );
    }

    public function find(string $versionId): StoredFormVersion
    {
        $record = $this->versionRecord($versionId);
        $nodes = $this->database->table('jwf_form_nodes')
            ->where('form_version_id', $versionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $nodeIds = $nodes->pluck('id')->all();

        $optionsByNode = [];
        if ($nodeIds !== []) {
            foreach ($this->database->table('jwf_input_options')
                ->whereIn('form_node_id', $nodeIds)
                ->orderBy('position')
                ->get() as $option) {
                $optionsByNode[$this->string($option->form_node_id)][] = $option;
            }
        }

        $profilesByNode = [];
        if ($nodeIds !== []) {
            $profiles = $this->database->table('jwf_form_node_profile_versions as pivot')
                ->join('jwf_validation_profile_versions as versions', 'versions.id', '=', 'pivot.profile_version_id')
                ->whereIn('pivot.form_node_id', $nodeIds)
                ->orderBy('pivot.position')
                ->select([
                    'pivot.form_node_id',
                    'versions.id',
                    'versions.profile_id',
                    'versions.number',
                    'versions.compatible_types',
                ])
                ->get();
            foreach ($profiles as $profile) {
                $profilesByNode[$this->string($profile->form_node_id)][] = $profile;
            }
        }

        $childrenByParent = [];
        $root = null;
        foreach ($nodes as $node) {
            if ($node->parent_id === null) {
                if ($root !== null) {
                    throw new RuntimeException('A form version must contain exactly one root node.');
                }
                $root = $node;
                continue;
            }
            $childrenByParent[$this->string($node->parent_id)][] = $node;
        }
        if (!$root instanceof stdClass) {
            throw new RuntimeException('The form version root node is missing.');
        }

        $rootNode = $this->hydrateNode($root, $childrenByParent, $optionsByNode, $profilesByNode);
        if (!$rootNode instanceof ContainerNode) {
            throw new RuntimeException('The form version root must be a container.');
        }

        return new StoredFormVersion(
            $this->string($record->template_id),
            $this->integer($record->number),
            new FormDocument(
                NodeId::fromString($this->string($record->id)),
                $rootNode,
                FormState::from($this->string($record->state)),
            ),
        );
    }

    public function cloneVersion(string $sourceVersionId): StoredFormVersion
    {
        $source = $this->find($sourceVersionId);

        return $this->database->connection()->transaction(function () use ($source): StoredFormVersion {
            $maximum = $this->database->table('jwf_form_versions')
                ->where('template_id', $source->templateId)
                ->lockForUpdate()
                ->max('number');
            $nextNumber = ($maximum === null ? 0 : $this->integer($maximum)) + 1;
            $copy = new FormDocument(NodeId::generate(), $source->document->root, FormState::Draft);
            $this->insertVersion($source->templateId, $nextNumber, $copy);

            return new StoredFormVersion($source->templateId, $nextNumber, $copy);
        });
    }

    public function publish(string $versionId): StoredFormVersion
    {
        $stored = $this->find($versionId);
        $published = $stored->document->publish();
        $this->database->table('jwf_form_versions')->where('id', $versionId)->update([
            'state' => FormState::Published->value,
            'updated_at' => now(),
        ]);

        return new StoredFormVersion($stored->templateId, $stored->number, $published);
    }

    public function archive(string $versionId): StoredFormVersion
    {
        $stored = $this->find($versionId);
        $archived = $stored->document->archive();
        $this->database->table('jwf_form_versions')->where('id', $versionId)->update([
            'state' => FormState::Archived->value,
            'updated_at' => now(),
        ]);

        return new StoredFormVersion($stored->templateId, $stored->number, $archived);
    }

    public function deleteVersion(string $versionId): void
    {
        if ($this->database->table('jwf_submissions')->where('form_version_id', $versionId)->exists()) {
            throw new FormHasSubmissionsException('A form version with submissions cannot be deleted.');
        }
        $this->database->table('jwf_form_versions')->where('id', $versionId)->delete();
    }

    public function deleteTemplate(string $templateId): void
    {
        $hasSubmissions = $this->database->table('jwf_submissions as submissions')
            ->join('jwf_form_versions as versions', 'versions.id', '=', 'submissions.form_version_id')
            ->where('versions.template_id', $templateId)
            ->exists();
        if ($hasSubmissions) {
            throw new FormHasSubmissionsException('A form template with submissions cannot be deleted.');
        }
        $this->database->table('jwf_form_templates')->where('id', $templateId)->delete();
    }

    private function insertVersion(string $templateId, int $number, FormDocument $document): void
    {
        $now = now();
        $this->database->table('jwf_form_versions')->insert([
            'id' => $document->id->toString(),
            'template_id' => $templateId,
            'number' => $number,
            'state' => $document->state->value,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $this->insertNode($document->id->toString(), $document->root, null);
    }

    private function insertNode(string $versionId, Node $node, ?string $parentId): void
    {
        $rowId = (string) Str::uuid();
        $attributes = $node instanceof InputNode || $node instanceof FormNode || $node instanceof ContainerNode
            ? $node->attributes->toArray()
            : [];
        $this->database->table('jwf_form_nodes')->insert([
            'id' => $rowId,
            'form_version_id' => $versionId,
            'node_id' => $node->id()->toString(),
            'parent_id' => $parentId,
            'kind' => $node instanceof InputNode ? 'input' : ($node instanceof FormNode ? 'form' : 'container'),
            'position' => $node->position(),
            'type' => $node instanceof InputNode ? $node->type->value : null,
            'name' => $node instanceof InputNode || $node instanceof FormNode ? $node->name : null,
            'label' => $node instanceof InputNode ? $node->label : null,
            'description' => $node instanceof InputNode ? $node->description : null,
            'attributes' => $this->encode($attributes),
            'configuration' => $this->encode($node instanceof InputNode ? $node->configuration : []),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($node instanceof InputNode) {
            foreach ($node->options as $position => $option) {
                $this->database->table('jwf_input_options')->insert([
                    'id' => (string) Str::uuid(),
                    'form_node_id' => $rowId,
                    'option_id' => $option->id->toString(),
                    'value' => $option->value,
                    'label' => $option->label,
                    'disabled' => $option->disabled,
                    'attributes' => $this->encode($option->attributes->toArray()),
                    'position' => $position,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($node->validationProfileVersions as $position => $profile) {
                $this->database->table('jwf_form_node_profile_versions')->insert([
                    'form_node_id' => $rowId,
                    'profile_version_id' => $profile->versionId->toString(),
                    'position' => $position,
                ]);
            }
        }

        if ($node instanceof FormNode || $node instanceof ContainerNode) {
            foreach ($node->children as $child) {
                $this->insertNode($versionId, $child, $rowId);
            }
        }
    }

    /**
     * @param array<string, list<stdClass>> $childrenByParent
     * @param array<string, list<stdClass>> $optionsByNode
     * @param array<string, list<stdClass>> $profilesByNode
     */
    private function hydrateNode(
        stdClass $record,
        array $childrenByParent,
        array $optionsByNode,
        array $profilesByNode,
    ): Node {
        $attributes = Attributes::fromArray($this->decode($this->string($record->attributes)));
        $children = [];
        foreach ($childrenByParent[$this->string($record->id)] ?? [] as $child) {
            $children[] = $this->hydrateNode($child, $childrenByParent, $optionsByNode, $profilesByNode);
        }

        return match ($this->string($record->kind)) {
            'container' => new ContainerNode(
                NodeId::fromString($this->string($record->node_id)),
                $this->integer($record->position),
                $children,
                $attributes,
            ),
            'form' => new FormNode(
                NodeId::fromString($this->string($record->node_id)),
                $this->integer($record->position),
                $this->string($record->name),
                $children,
                $attributes,
            ),
            'input' => new InputNode(
                NodeId::fromString($this->string($record->node_id)),
                $this->integer($record->position),
                InputType::from($this->string($record->type)),
                $this->string($record->name),
                $record->label === null ? null : $this->string($record->label),
                $record->description === null ? null : $this->string($record->description),
                $this->hydrateOptions($optionsByNode[$this->string($record->id)] ?? []),
                $this->decode($this->string($record->configuration)),
                $attributes,
                $this->hydrateProfiles($profilesByNode[$this->string($record->id)] ?? []),
            ),
            default => throw new RuntimeException('Unknown persisted node kind.'),
        };
    }

    /** @param list<stdClass> $records
     * @return list<Option>
     */
    private function hydrateOptions(array $records): array
    {
        return array_map(fn (stdClass $option): Option => new Option(
            NodeId::fromString($this->string($option->option_id)),
            $this->string($option->value),
            $this->string($option->label),
            $this->boolean($option->disabled),
            Attributes::fromArray($this->decode($this->string($option->attributes))),
        ), $records);
    }

    /** @param list<stdClass> $records
     * @return list<ValidationProfileVersionReference>
     */
    private function hydrateProfiles(array $records): array
    {
        return array_map(fn (stdClass $profile): ValidationProfileVersionReference => new ValidationProfileVersionReference(
            NodeId::fromString($this->string($profile->profile_id)),
            NodeId::fromString($this->string($profile->id)),
            $this->integer($profile->number),
            array_map(
                static fn (string $type): InputType => InputType::from($type),
                $this->stringList($this->string($profile->compatible_types)),
            ),
        ), $records);
    }

    private function versionRecord(string $versionId): stdClass
    {
        $record = $this->database->table('jwf_form_versions')->where('id', $versionId)->first();
        if (!$record instanceof stdClass) {
            throw new JwfLaravelException('Form version not found.');
        }

        return $record;
    }

    /** @param array<string, mixed> $value */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function decode(string $value): array
    {
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new JsonException('Persisted JSON must contain an object.');
        }

        $object = [];
        foreach ($decoded as $key => $item) {
            if (!is_string($key)) {
                throw new JsonException('Persisted JSON object keys must be strings.');
            }
            $object[$key] = $item;
        }

        return $object;
    }

    /** @return list<string> */
    private function stringList(string $value): array
    {
        $decoded = json_decode($value, true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($decoded) || !array_is_list($decoded)) {
            throw new JsonException('Persisted JSON must contain a list.');
        }
        foreach ($decoded as $item) {
            if (!is_string($item)) {
                throw new JsonException('Persisted JSON list must contain strings.');
            }
        }

        return $decoded;
    }

    private function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new JsonException('Persisted form data must contain strings.');
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (!is_int($value)) {
            throw new JsonException('Persisted form data must contain integers.');
        }

        return $value;
    }

    private function boolean(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 0 || $value === 1) {
            return (bool) $value;
        }

        throw new JsonException('Persisted form data must contain booleans.');
    }
}
