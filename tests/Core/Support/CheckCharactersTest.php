<?php

declare(strict_types=1);

namespace Simtabi\SIS\Tests\Support;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Simtabi\SIS\Support\CheckCharacters;

final class CheckCharactersTest extends TestCase
{
    /**
     * The Annex A specimen check characters. These are the golden vectors the docblock promised and the
     * prototype never had.
     *
     * @return array<string, array{string, string}>
     */
    public static function annexA(): array
    {
        return [
            'client' => ['SIM-CLT-100001', '9O'],
            'person' => ['SIM-PRS-100001', 'FA'],
            'product' => ['SIM-PRD-100001', 'H3'],
            'asset' => ['SIM-AST-100001', '8W'],
            'sow scoped' => ['SIM-SOW-ADIQ-000001', 'NZ'],
            'invoice scoped' => ['SIM-INV-ADIQ-000001', 'VY'],
        ];
    }

    #[DataProvider('annexA')]
    public function test_computes_the_annex_a_golden_vectors(string $core, string $expected): void
    {
        self::assertSame($expected, CheckCharacters::for($core));
        self::assertTrue(CheckCharacters::verify($core, $expected));
        self::assertFalse(CheckCharacters::verify($core, 'ZZ'));
    }

    public function test_separators_and_case_are_irrelevant(): void
    {
        self::assertSame(CheckCharacters::for('SIM-CLT-100001'), CheckCharacters::for('simclt100001'));
    }

    /**
     * The four claims the docblock makes, tested directly against the check function: no single-character
     * substitution, adjacent transposition, jump transposition, or twin error leaves the check unchanged.
     */
    public function test_detects_every_error_class(): void
    {
        $alphabet = str_split('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ');

        $cores = [
            'SIMCLT100001',        // Form G, 6-digit
            'SIMPRS100000000',     // Form G, 9-digit
            'SIMINVADIQ000001',    // Form S, alias scope
            'SIMTKTAB4Z9000042',   // Form S, alphanumeric scope
        ];

        foreach ($cores as $core) {
            $expected = CheckCharacters::for($core);
            $length = strlen($core);

            // Single-character substitution.
            for ($i = 0; $i < $length; $i++) {
                foreach ($alphabet as $c) {
                    if ($c === $core[$i]) {
                        continue;
                    }

                    $mutated = substr_replace($core, $c, $i, 1);
                    self::assertNotSame($expected, CheckCharacters::for($mutated), "substitution at {$i} in {$core}");
                }
            }

            // Adjacent transposition (i, i+1).
            for ($i = 0; $i < $length - 1; $i++) {
                if ($core[$i] === $core[$i + 1]) {
                    continue;
                }

                $mutated = $core;
                [$mutated[$i], $mutated[$i + 1]] = [$mutated[$i + 1], $mutated[$i]];
                self::assertNotSame($expected, CheckCharacters::for($mutated), "adjacent transposition at {$i} in {$core}");
            }

            // Jump transposition (i, i+2): aXb -> bXa.
            for ($i = 0; $i < $length - 2; $i++) {
                if ($core[$i] === $core[$i + 2]) {
                    continue;
                }

                $mutated = $core;
                [$mutated[$i], $mutated[$i + 2]] = [$mutated[$i + 2], $mutated[$i]];
                self::assertNotSame($expected, CheckCharacters::for($mutated), "jump transposition at {$i} in {$core}");
            }

            // Twin error (aa -> bb).
            for ($i = 0; $i < $length - 1; $i++) {
                if ($core[$i] !== $core[$i + 1]) {
                    continue;
                }

                foreach ($alphabet as $c) {
                    if ($c === $core[$i]) {
                        continue;
                    }

                    $mutated = substr_replace($core, $c . $c, $i, 2);
                    self::assertNotSame($expected, CheckCharacters::for($mutated), "twin error at {$i} in {$core}");
                }
            }
        }
    }
}
