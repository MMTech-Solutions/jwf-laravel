<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions\Repositories;

use Mmt\Jwf\Submissions\Contracts\SubmissionRepository;
use Mmt\Jwf\Submissions\Domain\Submission;

final class NonPersistingSubmissionRepository implements SubmissionRepository
{
    public function store(Submission $submission): Submission
    {
        return $submission;
    }
}
