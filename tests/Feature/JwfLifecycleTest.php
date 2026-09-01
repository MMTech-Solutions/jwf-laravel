<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Mmt\Jwf\Forms\Domain\FormDocument;
use Mmt\Jwf\Forms\Domain\Input\InputType;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\ValidationProfiles\Domain\RuleDefinition;
use Mmt\JwfLaravel\Exceptions\FormHasSubmissionsException;
use Mmt\JwfLaravel\Tests\TestCase;

final class JwfLifecycleTest extends TestCase
{
    public function testItClonesPublishesValidatesSubmitsAndProtectsDefinitions(): void
    {
        $jwf = $this->jwf();
        $profile = $jwf->profiles()->create('required-text');
        $profileVersion = $jwf->profiles()->createVersion(
            $profile->id->toString(),
            [InputType::Text],
            [new RuleDefinition('required'), new RuleDefinition('string')],
        );
        [$draft, $form, $input] = $this->document(InputType::Text, [$profileVersion->reference()]);

        $stored = $jwf->forms()->create('Contact', $draft);
        $copy = $jwf->forms()->cloneVersion($stored->document->id->toString());

        self::assertSame(2, $copy->number);
        self::assertSame($stored->document->root->toArray(), $copy->document->root->toArray());
        self::assertNotSame($stored->document->id->toString(), $copy->document->id->toString());

        $published = $jwf->forms()->publish($stored->document->id->toString());
        self::assertFalse($jwf->validate($published->document->id->toString(), $form->id->toString(), [])->isValid());

        $result = $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'Ada'],
        );
        self::assertTrue($result->isValid());
        self::assertNotNull($result->submission);
        self::assertDatabaseCount('jwf_submissions', 1);
        self::assertDatabaseCount('jwf_submission_values', 1);

        try {
            $jwf->forms()->deleteVersion($published->document->id->toString());
            self::fail('A version with submissions was deleted.');
        } catch (FormHasSubmissionsException) {
            self::assertDatabaseCount('jwf_form_versions', 2);
        }

        $jwf->deleteSubmission($result->submission->id->toString());
        $jwf->forms()->deleteVersion($published->document->id->toString());
        self::assertDatabaseCount('jwf_form_versions', 1);
    }

    public function testItEncryptsPasswordValuesAtRest(): void
    {
        $jwf = $this->jwf();
        [$draft, $form, $input] = $this->document(InputType::Password);
        $published = $jwf->forms()->publish($jwf->forms()->create('Secret', $draft)->document->id->toString());

        $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'top-secret'],
        );

        $row = DB::table('jwf_submission_values')->first();
        self::assertNotNull($row);
        self::assertTrue((bool) $row->sensitive);
        self::assertIsString($row->value);
        self::assertStringNotContainsString('top-secret', $row->value);
    }

    public function testItPersistsValidatesAndSubmitsUrlInputs(): void
    {
        $jwf = $this->jwf();
        [$draft, $form, $input] = $this->document(InputType::Url);

        $stored = $jwf->forms()->create('Website', $draft);
        self::assertSame(
            'url',
            DB::table('jwf_form_nodes')->where('kind', 'input')->value('type'),
        );
        self::assertDatabaseHas('jwf_validation_profiles', ['name' => 'jwf.default.url']);
        self::assertDatabaseHas('jwf_validation_profile_versions', [
            'number' => 1,
            'compatible_types' => json_encode(['url'], JSON_THROW_ON_ERROR),
            'rules' => json_encode([['name' => 'url', 'parameters' => []]], JSON_THROW_ON_ERROR),
        ]);
        self::assertSame(
            $stored->document->root->toArray(),
            $jwf->forms()->get($stored->document->id->toString())->document->root->toArray(),
        );
        $stored = $jwf->forms()->saveDraft($stored->document);
        self::assertSame(
            1,
            DB::table('jwf_form_node_profile_versions as pivot')
                ->join('jwf_form_nodes as nodes', 'nodes.id', '=', 'pivot.form_node_id')
                ->where('nodes.form_version_id', $stored->document->id->toString())
                ->count(),
        );

        $published = $jwf->forms()->publish($stored->document->id->toString());
        $invalid = $jwf->validate(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'not-a-url'],
        );
        self::assertFalse($invalid->isValid());

        $result = $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'https://example.com/path'],
        );
        self::assertTrue($result->isValid());
        self::assertNotNull($result->submission);
        self::assertSame('https://example.com/path', $result->submission->values[0]->value);
    }

    public function testItPersistsValidatesAndSubmitsEmailInputs(): void
    {
        $jwf = $this->jwf();
        [$draft, $form, $input] = $this->document(InputType::Email);

        $stored = $jwf->forms()->create('Contact email', $draft);
        self::assertDatabaseHas('jwf_validation_profiles', ['name' => 'jwf.default.email']);
        self::assertDatabaseHas('jwf_validation_profile_versions', [
            'number' => 1,
            'compatible_types' => json_encode(['email'], JSON_THROW_ON_ERROR),
            'rules' => json_encode([['name' => 'email', 'parameters' => []]], JSON_THROW_ON_ERROR),
        ]);

        $published = $jwf->forms()->publish($stored->document->id->toString());
        $invalid = $jwf->validate(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'not-an-email'],
        );
        self::assertFalse($invalid->isValid());

        $result = $jwf->submit(
            $published->document->id->toString(),
            $form->id->toString(),
            [$input->name => 'ada@example.com'],
        );
        self::assertTrue($result->isValid());
        self::assertNotNull($result->submission);
        self::assertSame('ada@example.com', $result->submission->values[0]->value);
    }

    public function testItCanFaithfullyCloneEveryVersionState(): void
    {
        $jwf = $this->jwf();
        [$draft] = $this->document(InputType::Text);
        $source = $jwf->forms()->create('Versions', $draft);

        $draftCopy = $jwf->forms()->cloneVersion($source->document->id->toString());
        $published = $jwf->forms()->publish($source->document->id->toString());
        $publishedCopy = $jwf->forms()->cloneVersion($published->document->id->toString());
        $archived = $jwf->forms()->archive($published->document->id->toString());
        $archivedCopy = $jwf->forms()->cloneVersion($archived->document->id->toString());

        foreach ([$draftCopy, $publishedCopy, $archivedCopy] as $copy) {
            self::assertSame($source->document->root->toArray(), $copy->document->root->toArray());
            self::assertSame('draft', $copy->document->state->value);
            self::assertNotSame($source->document->id->toString(), $copy->document->id->toString());
        }
        self::assertSame([2, 3, 4], [$draftCopy->number, $publishedCopy->number, $archivedCopy->number]);
    }

    public function testItDoesNotUseAnAggregateToLockFormVersionsWhenCloning(): void
    {
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = strtolower($query->sql);
        });

        [$draft] = $this->document(InputType::Text);
        $source = $this->jwf()->forms()->create('Clone lock', $draft);
        $this->jwf()->forms()->cloneVersion($source->document->id->toString());

        self::assertFalse(collect($queries)->contains(
            static fn (string $sql): bool => str_contains($sql, 'max("number")')
                && str_contains($sql, 'jwf_form_versions'),
        ));
    }

    /** @param list<\Mmt\Jwf\ValidationProfiles\Domain\ValidationProfileVersionReference> $profiles
     * @return array{FormDocument, FormNode, InputNode}
     */
    private function document(InputType $type, array $profiles = []): array
    {
        $input = new InputNode(NodeId::generate(), 0, $type, 'value', validationProfileVersions: $profiles);
        $form = new FormNode(NodeId::generate(), 0, 'main', [$input]);
        $document = new FormDocument(
            NodeId::generate(),
            new ContainerNode(NodeId::generate(), 0, [$form]),
        );

        return [$document, $form, $input];
    }
}
