<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions;

use Illuminate\Database\DatabaseManager;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Mmt\Jwf\Forms\Domain\Input\FileReference;
use Mmt\Jwf\Forms\Domain\Node\ContainerNode;
use Mmt\Jwf\Forms\Domain\Node\FormNode;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\Jwf\Submissions\Application\SubmitForm;
use Mmt\Jwf\Submissions\Contracts\Clock;
use Mmt\Jwf\Submissions\Contracts\SubmissionRepository;
use Mmt\Jwf\Submissions\Contracts\ValidationProfileVersionResolver;
use Mmt\Jwf\Submissions\Domain\SubmittedValue;
use Mmt\Jwf\Submissions\Domain\SubmissionResult;
use Mmt\Jwf\ValidationProfiles\Contracts\ValidationEngine;
use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Authorization\JwfOperation;
use Mmt\JwfLaravel\Contracts\JwfAuthorizer;
use Mmt\JwfLaravel\Exceptions\JwfLaravelException;
use Mmt\JwfLaravel\Files\ArtifactManager;
use Mmt\JwfLaravel\Forms\Repositories\FormRepository;
use Mmt\JwfLaravel\Submissions\Data\ValidationOutcome;
use Mmt\JwfLaravel\Submissions\Repositories\NonPersistingSubmissionRepository;
use Throwable;

final readonly class SubmissionManager
{
    private SubmitForm $validator;

    private SubmitForm $submitter;

    public function __construct(
        private FormRepository $forms,
        private ArtifactManager $artifacts,
        private DatabaseManager $database,
        private JwfAuthorizer $authorizer,
        SubmissionRepository $submissions,
        Clock $clock,
        ValidationProfileVersionResolver $profileResolver,
        ValidationEngine $validationEngine,
    ) {
        $this->validator = new SubmitForm(
            new NonPersistingSubmissionRepository(),
            $clock,
            $profileResolver,
            $validationEngine,
        );
        $this->submitter = new SubmitForm($submissions, $clock, $profileResolver, $validationEngine);
    }

    /** @param Request|array<string, mixed> $input */
    public function validate(
        string $formVersionId,
        string $formId,
        Request|array $input,
        AuthorizationContext $context = new AuthorizationContext(),
    ): ValidationOutcome {
        $this->authorizer->authorize(JwfOperation::Submit, $context);
        $stored = $this->forms->find($formVersionId);
        $values = $this->mapValues($stored->document->root, $formId, $this->inputArray($input), false);
        $result = $this->validator->submit($stored->document, NodeId::fromString($formId), $values);

        return new ValidationOutcome($result->errors);
    }

    /** @param Request|array<string, mixed> $input */
    public function submit(
        string $formVersionId,
        string $formId,
        Request|array $input,
        AuthorizationContext $context = new AuthorizationContext(),
    ): SubmissionResult {
        $this->authorizer->authorize(JwfOperation::Submit, $context);
        $stored = $this->forms->find($formVersionId);
        $staged = [];
        try {
            $values = $this->mapValues(
                $stored->document->root,
                $formId,
                $this->inputArray($input),
                true,
                $staged,
            );
            $result = $this->submitter->submit($stored->document, NodeId::fromString($formId), $values);
            if (!$result->isValid()) {
                $this->discard($staged);
            }

            return $result;
        } catch (Throwable $exception) {
            $this->discard($staged);
            throw $exception;
        }
    }

    public function delete(
        string $submissionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): void {
        $this->authorizer->authorize(JwfOperation::DeleteSubmission, $context);
        $deleted = $this->database->table('jwf_submissions')->where('id', $submissionId)->delete();
        if ($deleted === 0) {
            throw new JwfLaravelException('Submission not found.');
        }
    }

    /** @param array<string, mixed> $input
     * @param list<string> $staged
     * @return list<SubmittedValue>
     */
    private function mapValues(
        ContainerNode $root,
        string $formId,
        array $input,
        bool $storeFiles,
        array &$staged = [],
    ): array {
        $form = $this->findForm($root, $formId);
        if ($form === null) {
            throw new JwfLaravelException('The requested form does not belong to the form version.');
        }
        $values = [];
        foreach ($this->inputsIn($form) as $node) {
            if (!array_key_exists($node->name, $input)) {
                continue;
            }
            $value = $input[$node->name];
            if ($value instanceof UploadedFile) {
                $reference = $storeFiles
                    ? $this->artifacts->stage($value, $node)
                    : $this->artifacts->referenceForValidation($value, $node);
                if ($storeFiles) {
                    $staged[] = $reference->id->toString();
                }
                $value = $reference;
            }
            if (!$this->isCoreValue($value)) {
                throw new JwfLaravelException("Input [{$node->name}] has an unsupported transport value.");
            }
            $values[] = new SubmittedValue($node->id, $this->coreValue($value));
        }

        return $values;
    }

    /** @param Request|array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function inputArray(Request|array $input): array
    {
        $values = is_array($input) ? $input : $input->all();
        $result = [];
        foreach ($values as $key => $value) {
            if (!is_string($key)) {
                throw new JwfLaravelException('Request input keys must be strings.');
            }
            $result[$key] = $value;
        }

        return $result;
    }

    private function findForm(ContainerNode $container, string $formId): ?FormNode
    {
        foreach ($container->children as $child) {
            if ($child instanceof FormNode && $child->id->toString() === $formId) {
                return $child;
            }
            if ($child instanceof ContainerNode) {
                $found = $this->findForm($child, $formId);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** @return list<InputNode> */
    private function inputsIn(FormNode|ContainerNode $parent): array
    {
        $inputs = [];
        foreach ($parent->children as $child) {
            if ($child instanceof InputNode) {
                $inputs[] = $child;
            } elseif ($child instanceof ContainerNode) {
                array_push($inputs, ...$this->inputsIn($child));
            }
        }

        return $inputs;
    }

    private function isCoreValue(mixed $value): bool
    {
        return $value === null
            || is_string($value)
            || is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value instanceof FileReference;
    }

    private function coreValue(mixed $value): string|int|float|bool|FileReference|null
    {
        if ($value === null || is_string($value) || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }
        if ($value instanceof FileReference) {
            return $value;
        }

        throw new JwfLaravelException('Unsupported submission value.');
    }

    /** @param list<string> $artifactIds */
    private function discard(array $artifactIds): void
    {
        foreach ($artifactIds as $artifactId) {
            try {
                $this->artifacts->discard($artifactId);
            } catch (Throwable) {
                // Preserve the original failure; the artifact remains visible as unreferenced for manual cleanup.
            }
        }
    }
}
