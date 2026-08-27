<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Files;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Http\UploadedFile;
use Mmt\Jwf\Forms\Domain\Input\FileReference;
use Mmt\Jwf\Forms\Domain\Node\InputNode;
use Mmt\Jwf\Shared\Domain\NodeId;
use Mmt\JwfLaravel\Authorization\AuthorizationContext;
use Mmt\JwfLaravel\Authorization\JwfOperation;
use Mmt\JwfLaravel\Contracts\FileStorage;
use Mmt\JwfLaravel\Contracts\JwfAuthorizer;
use Mmt\JwfLaravel\Exceptions\ArtifactNotFoundException;
use Mmt\JwfLaravel\Exceptions\FileRejectedException;
use Mmt\JwfLaravel\Files\Data\FileArtifact;
use stdClass;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class ArtifactManager
{
    public function __construct(
        private DatabaseManager $database,
        private FileStorage $storage,
        private Repository $config,
        private JwfAuthorizer $authorizer,
    ) {
    }

    public function referenceForValidation(UploadedFile $file, InputNode $input): FileReference
    {
        $this->assertAllowed($file, $input);

        return new FileReference(
            NodeId::generate(),
            $file->getClientOriginalName(),
            (string) $file->getMimeType(),
            (int) $file->getSize(),
        );
    }

    public function stage(UploadedFile $file, InputNode $input): FileReference
    {
        $this->assertAllowed($file, $input);
        $id = NodeId::generate();
        $stored = $this->storage->store($file, $id->toString());
        try {
            $this->database->table('jwf_file_artifacts')->insert([
                'id' => $id->toString(),
                'submission_value_id' => null,
                'disk' => $stored->disk,
                'path' => $stored->path,
                'name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $this->storage->delete($stored->disk, $stored->path);
            throw $exception;
        }

        return new FileReference(
            $id,
            $file->getClientOriginalName(),
            (string) $file->getMimeType(),
            (int) $file->getSize(),
        );
    }

    public function discard(string $artifactId): void
    {
        $record = $this->record($artifactId);
        $this->storage->delete($this->string($record->disk), $this->string($record->path));
        $this->database->table('jwf_file_artifacts')->where('id', $artifactId)->delete();
    }

    public function find(
        string $artifactId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): FileArtifact {
        $this->authorizer->authorize(JwfOperation::ReadArtifact, $context);

        return $this->toData($this->record($artifactId));
    }

    /** @return list<FileArtifact> */
    public function unreferenced(
        AuthorizationContext $context = new AuthorizationContext(),
    ): array {
        $this->authorizer->authorize(JwfOperation::ReadArtifact, $context);

        $artifacts = $this->database->table('jwf_file_artifacts')
            ->whereNull('submission_value_id')
            ->orderBy('created_at')
            ->get()
            ->map(fn (stdClass $record): FileArtifact => $this->toData($record))
            ->all();

        return array_values($artifacts);
    }

    public function download(
        string $artifactId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): StreamedResponse {
        $this->authorizer->authorize(JwfOperation::ReadArtifact, $context);
        $record = $this->record($artifactId);

        return $this->storage->download(
            $this->string($record->disk),
            $this->string($record->path),
            $this->string($record->name),
        );
    }

    public function delete(
        string $artifactId,
        AuthorizationContext $context = new AuthorizationContext(),
    ): void {
        $this->authorizer->authorize(JwfOperation::DeleteArtifact, $context);
        $this->discard($artifactId);
    }

    private function assertAllowed(UploadedFile $file, InputNode $input): void
    {
        if (!$file->isValid()) {
            throw new FileRejectedException('The uploaded file is invalid.');
        }
        $configuredMax = $this->config->get('jwf.files.max_size_kb', 10240);
        if (!is_int($configuredMax)) {
            throw new FileRejectedException('The configured file size limit must be an integer.');
        }
        $maxKilobytes = $configuredMax;
        if ((int) $file->getSize() > $maxKilobytes * 1024) {
            throw new FileRejectedException('The uploaded file exceeds the configured size limit.');
        }
        $mimeTypes = $this->config->get('jwf.files.allowed_mime_types', []);
        if (is_array($mimeTypes) && $mimeTypes !== [] && !in_array($file->getMimeType(), $mimeTypes, true)) {
            throw new FileRejectedException('The uploaded file MIME type is not allowed.');
        }
        $extensions = $this->config->get('jwf.files.allowed_extensions', []);
        if (is_array($extensions)
            && $extensions !== []
            && !in_array(strtolower($file->getClientOriginalExtension()), $extensions, true)
        ) {
            throw new FileRejectedException('The uploaded file extension is not allowed.');
        }

        $inputMax = $input->configuration['maxSizeKb'] ?? null;
        if ($inputMax !== null && (!is_int($inputMax) || $inputMax < 1)) {
            throw new FileRejectedException('The input file size limit is invalid.');
        }
        if (is_int($inputMax) && (int) $file->getSize() > $inputMax * 1024) {
            throw new FileRejectedException('The uploaded file exceeds the input size limit.');
        }
        $inputMimeTypes = $this->configurationStrings($input, 'allowedMimeTypes');
        if ($inputMimeTypes !== [] && !in_array($file->getMimeType(), $inputMimeTypes, true)) {
            throw new FileRejectedException('The uploaded file MIME type is not allowed by the input.');
        }
        $inputExtensions = $this->configurationStrings($input, 'allowedExtensions');
        if ($inputExtensions !== []
            && !in_array(strtolower($file->getClientOriginalExtension()), $inputExtensions, true)
        ) {
            throw new FileRejectedException('The uploaded file extension is not allowed by the input.');
        }
    }

    private function record(string $artifactId): stdClass
    {
        $record = $this->database->table('jwf_file_artifacts')->where('id', $artifactId)->first();
        if (!$record instanceof stdClass) {
            throw new ArtifactNotFoundException('File artifact not found.');
        }

        return $record;
    }

    private function toData(stdClass $record): FileArtifact
    {
        return new FileArtifact(
            $this->string($record->id),
            $record->submission_value_id === null ? null : $this->string($record->submission_value_id),
            $this->string($record->name),
            $this->string($record->mime_type),
            $this->integer($record->size),
        );
    }

    private function string(mixed $value): string
    {
        if (!is_string($value)) {
            throw new ArtifactNotFoundException('Persisted artifact data is invalid.');
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (!is_int($value)) {
            throw new ArtifactNotFoundException('Persisted artifact data is invalid.');
        }

        return $value;
    }

    /** @return list<string> */
    private function configurationStrings(InputNode $input, string $key): array
    {
        $values = $input->configuration[$key] ?? [];
        if (!is_array($values) || !array_is_list($values)) {
            throw new FileRejectedException("Input file configuration [$key] must be a list.");
        }
        foreach ($values as $value) {
            if (!is_string($value) || $value === '') {
                throw new FileRejectedException("Input file configuration [$key] contains an invalid value.");
            }
        }

        return $values;
    }
}
