<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Forms\Data;

use Mmt\Jwf\Forms\Domain\FormDocument;

final readonly class StoredFormVersion
{
    public function __construct(
        public string $templateId,
        public int $number,
        public FormDocument $document,
    ) {
    }
}
