<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel;

use Illuminate\Http\Request;
use Mmt\Jwf\Submissions\Domain\SubmissionResult;
use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Files\ArtifactManager;
use Mmt\JwfLaravel\Forms\FormManager;
use Mmt\JwfLaravel\Submissions\Data\ValidationOutcome;
use Mmt\JwfLaravel\Submissions\SubmissionManager;
use Mmt\JwfLaravel\ValidationProfiles\ValidationProfileManager;

final readonly class JwfManager
{
    public function __construct(
        private FormManager $forms,
        private ValidationProfileManager $profiles,
        private SubmissionManager $submissions,
        private ArtifactManager $artifacts,
    ) {
    }

    public function forms(): FormManager
    {
        return $this->forms;
    }

    public function profiles(): ValidationProfileManager
    {
        return $this->profiles;
    }

    public function artifacts(): ArtifactManager
    {
        return $this->artifacts;
    }

    /** @param Request|array<string, mixed> $input */
    public function validate(
        string $formVersionId,
        string $formId,
        Request|array $input,
        AuthorizationContext $context = new AuthorizationContext(),
    ): ValidationOutcome {
        return $this->submissions->validate($formVersionId, $formId, $input, $context);
    }

    /** @param Request|array<string, mixed> $input */
    public function submit(
        string $formVersionId,
        string $formId,
        Request|array $input,
        AuthorizationContext $context = new AuthorizationContext(),
    ): SubmissionResult {
        return $this->submissions->submit($formVersionId, $formId, $input, $context);
    }

    public function deleteSubmission(
        string $submissionId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): void {
        $this->submissions->delete($submissionId, $context);
    }
}
