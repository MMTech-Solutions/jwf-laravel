<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Files\Data;

final readonly class FileArtifact
{
    public function __construct(
        public string $id,
        public ?string $submissionValueId,
        public string $name,
        public string $mimeType,
        public int $size,
    ) {
    }
}
