<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Submissions\Repositories;

use Illuminate\Contracts\Encryption\Encrypter;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;
use JsonException;
use Mmt\Jwf\Forms\Domain\Input\FileReference;
use Mmt\Jwf\Submissions\Contracts\SubmissionRepository;
use Mmt\Jwf\Submissions\Domain\Submission;
use Mmt\JwfLaravel\Exceptions\ArtifactNotFoundException;

final readonly class EloquentSubmissionRepository implements SubmissionRepository
{
    public function __construct(private DatabaseManager $database, private Encrypter $encrypter)
    {
    }

    public function store(Submission $submission): Submission
    {
        return $this->database->connection()->transaction(function () use ($submission): Submission {
            $now = now();
            $this->database->table('jwf_submissions')->insert([
                'id' => $submission->id->toString(),
                'form_version_id' => $submission->documentId->toString(),
                'form_id' => $submission->formId->toString(),
                'submitted_at' => $submission->submittedAt,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($submission->values as $position => $value) {
                $valueId = (string) Str::uuid();
                $encoded = $this->encode($value->value);
                $this->database->table('jwf_submission_values')->insert([
                    'id' => $valueId,
                    'submission_id' => $submission->id->toString(),
                    'input_id' => $value->inputId->toString(),
                    'value' => $value->sensitive ? $this->encrypter->encrypt($encoded, false) : $encoded,
                    'sensitive' => $value->sensitive,
                    'position' => $position,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                if ($value->value instanceof FileReference) {
                    $updated = $this->database->table('jwf_file_artifacts')
                        ->where('id', $value->value->id->toString())
                        ->whereNull('submission_value_id')
                        ->update(['submission_value_id' => $valueId, 'updated_at' => $now]);
                    if ($updated !== 1) {
                        throw new ArtifactNotFoundException('The submitted file artifact is missing or already attached.');
                    }
                }
            }

            return $submission;
        });
    }

    /** @param string|int|float|bool|FileReference|null $value */
    private function encode(string|int|float|bool|FileReference|null $value): string
    {
        $canonical = $value instanceof FileReference ? $value->toArray() : $value;

        try {
            return json_encode($canonical, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        } catch (JsonException $exception) {
            throw new JsonException('A submission value could not be encoded.', previous: $exception);
        }
    }
}
