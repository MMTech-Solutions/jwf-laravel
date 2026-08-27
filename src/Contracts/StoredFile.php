<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Contracts;

final readonly class StoredFile
{
    public function __construct(public string $disk, public string $path)
    {
    }
}
