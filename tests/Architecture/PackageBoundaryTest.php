<?php

declare(strict_types=1);

namespace Mmt\JwfLaravel\Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class PackageBoundaryTest extends TestCase
{
    public function testCoreDoesNotDependOnLaravel(): void
    {
        $core = dirname(__DIR__, 3).'/jwf-core/src';
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($core));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            self::assertStringNotContainsString('Illuminate\\', $contents, $file->getPathname());
        }
    }
}
