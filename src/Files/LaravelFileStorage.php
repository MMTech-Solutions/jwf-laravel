<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Files;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Filesystem\FilesystemManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Mmt\JwfLaravel\Contracts\FileStorage;
use Mmt\JwfLaravel\Contracts\StoredFile;
use Mmt\JwfLaravel\Exceptions\JwfLaravelException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final readonly class LaravelFileStorage implements FileStorage
{
    public function __construct(private FilesystemManager $filesystems, private Repository $config)
    {
    }

    public function store(UploadedFile $file, string $id): StoredFile
    {
        $disk = $this->configString('jwf.disk', 'local');
        $directory = trim($this->configString('jwf.directory', 'jwf'), '/');
        $path = $this->filesystems->disk($disk)->putFileAs($directory, $file, $id);
        if ($path === false) {
            throw new JwfLaravelException('The uploaded file could not be stored.');
        }

        return new StoredFile($disk, $path);
    }

    public function delete(string $disk, string $path): void
    {
        if (!$this->filesystems->disk($disk)->delete($path)) {
            throw new JwfLaravelException('The stored file could not be deleted.');
        }
    }

    public function download(string $disk, string $path, string $name): StreamedResponse
    {
        $filesystem = $this->filesystems->disk($disk);
        if (!$filesystem instanceof FilesystemAdapter) {
            throw new JwfLaravelException('The configured filesystem does not support downloads.');
        }

        return $filesystem->download($path, $name);
    }

    private function configString(string $key, string $default): string
    {
        $value = $this->config->get($key, $default);
        if (!is_string($value) || $value === '') {
            throw new JwfLaravelException("Configuration [$key] must be a non-empty string.");
        }

        return $value;
    }
}
