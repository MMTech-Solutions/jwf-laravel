<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Contracts;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface FileStorage
{
    public function store(UploadedFile $file, string $id): StoredFile;

    public function delete(string $disk, string $path): void;

    public function download(string $disk, string $path, string $name): StreamedResponse;
}
