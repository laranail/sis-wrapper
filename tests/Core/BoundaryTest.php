<?php

declare(strict_types=1);

namespace Simtabi\SIS\Tests;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * The load-bearing architectural guarantee: the core is pure PHP with zero framework dependencies. This is
 * the test equivalent of `composer why illuminate/support` returning nothing for simtabi/sis — asserted at
 * both the manifest level (what it declares) and the source level (what it imports), so a stray `use
 * Illuminate\...` can never slip the boundary that deptrac guards in the shell.
 */
final class BoundaryTest extends TestCase
{
    private const array FORBIDDEN_PREFIXES = ['Illuminate\\', 'Laravel\\', 'Symfony\\', 'Orchestra\\'];

    public function test_the_core_manifest_requires_only_php(): void
    {
        $manifest = $this->coreManifest();

        /** @var array<string, string> $require */
        $require = $manifest['require'] ?? [];

        self::assertSame(['php'], array_keys($require), 'The core must require nothing but PHP itself.');
        self::assertSame('^8.5', $require['php']);
    }

    public function test_no_core_source_file_imports_a_framework_namespace(): void
    {
        $offenders = [];

        foreach ($this->coreSourceFiles() as $file) {
            $contents = (string) file_get_contents($file->getPathname());

            foreach (self::FORBIDDEN_PREFIXES as $prefix) {
                if (preg_match('/^use\s+' . preg_quote($prefix, '/') . '/m', $contents) === 1) {
                    $offenders[] = $file->getPathname() . ' imports ' . $prefix;
                }
            }
        }

        self::assertSame([], $offenders, "A pure-core file imports a framework namespace:\n" . implode("\n", $offenders));
    }

    /** @return array<string, mixed> */
    private function coreManifest(): array
    {
        $path = dirname(__DIR__, 2) . '/src/Core/composer.json';
        /** @var array<string, mixed> $decoded */
        $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return $decoded;
    }

    /** @return iterable<SplFileInfo> */
    private function coreSourceFiles(): iterable
    {
        $root = dirname(__DIR__, 2) . '/src/Core/src';

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->getExtension() === 'php') {
                yield $file;
            }
        }
    }
}
