<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions\Data;

use Mmt\Jwf\Submissions\Domain\SubmissionError;

final readonly class ValidationOutcome
{
    /** @param list<SubmissionError> $errors */
    public function __construct(public array $errors)
    {
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }
}
