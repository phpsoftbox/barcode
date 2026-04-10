<?php

declare(strict_types=1);

namespace PhpSoftBox\Barcode\Tests;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use function file_put_contents;
use function getenv;
use function in_array;
use function is_dir;
use function is_string;
use function mkdir;
use function preg_replace;
use function rmdir;
use function sprintf;
use function strtolower;
use function trim;
use function unlink;

final class TestArtifactStorage
{
    private const ARTIFACTS_DIR = __DIR__ . '/../local/tests/barcode';

    public static function initialize(): void
    {
        if (!self::artifactsEnabled()) {
            return;
        }

        self::clearArtifactsDirectory();
    }

    public static function save(string $name, string $extension, string $content): void
    {
        if (!self::artifactsEnabled()) {
            return;
        }

        if (!is_dir(self::ARTIFACTS_DIR)) {
            mkdir(self::ARTIFACTS_DIR, 0775, true);
        }

        $safeName = preg_replace('/[^a-z0-9\\-_]+/i', '-', $name);
        if (!is_string($safeName) || $safeName === '') {
            $safeName = 'artifact';
        }

        $safeExtension = trim($extension, " \t\n\r\0\x0B.");
        if ($safeExtension === '') {
            $safeExtension = 'bin';
        }

        $filename = sprintf('%s/%s.%s', self::ARTIFACTS_DIR, $safeName, $safeExtension);
        file_put_contents($filename, $content);
    }

    private static function clearArtifactsDirectory(): void
    {
        if (is_dir(self::ARTIFACTS_DIR)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator(self::ARTIFACTS_DIR, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST,
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    rmdir($item->getPathname());
                    continue;
                }

                unlink($item->getPathname());
            }
        }

        if (!is_dir(self::ARTIFACTS_DIR)) {
            mkdir(self::ARTIFACTS_DIR, 0775, true);
        }
    }

    private static function artifactsEnabled(): bool
    {
        $value = getenv('BARCODE_TEST_SAVE_ARTIFACTS');
        if (!is_string($value)) {
            return true;
        }

        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            return true;
        }

        return !in_array($normalized, ['0', 'false', 'off', 'no'], true);
    }
}
