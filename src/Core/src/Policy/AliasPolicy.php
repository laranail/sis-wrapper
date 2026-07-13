<?php

declare(strict_types=1);

namespace Simtabi\SIS\Policy;

use Simtabi\SIS\Identifier\AliasCandidates;

/**
 * Derives human-memorable aliases from a legal entity name — SIM-STD-0001:2026 §5.2.
 *
 * Four letters give 456,976 combinations, so the space was never the constraint; the scarce resource is
 * DERIVABLE, MEMORABLE codes. The policy therefore WIDENS BEFORE IT MANGLES: it exhausts every 4-char
 * candidate, then 5, then 6, and only then falls back to a numeric discriminator. `ACMX` still reads like
 * Acme; `ACME2` reads like a database error.
 *
 * `candidates()` is pure and deterministic — the ranking never leaves the core. `choose()` (on the
 * returned `AliasCandidates`) picks the first free candidate given the set of taken aliases the shell
 * supplies. Whether a candidate is free is a register question this policy never asks.
 */
final class AliasPolicy
{
    /** Aliases that collide with our own conventions — §5.3. */
    private const array RESERVED = [
        'SIMT', 'PROS', 'TEST', 'NULL', 'VOID', 'TEMP',
        'DEMO', 'NONE', 'ADMIN', 'ROOT', 'SYST',
    ];

    private const array LEGAL_SUFFIXES = [
        'LLC', 'INC', 'INCORPORATED', 'LTD', 'LIMITED', 'CORP', 'CORPORATION',
        'CO', 'COMPANY', 'GMBH', 'PLC', 'SA', 'SAS', 'BV', 'NV', 'AB', 'AG',
        'OY', 'AS', 'PTY', 'LLP', 'LP', 'PC', 'PLLC', 'SARL', 'SRL', 'SPA',
        'KK', 'PBC',
    ];

    private const array GENERIC_WORDS = [
        'HOLDINGS', 'GROUP', 'PARTNERS', 'VENTURES', 'SOLUTIONS', 'SERVICES',
        'TECHNOLOGIES', 'TECHNOLOGY', 'CONSULTING', 'SYSTEMS', 'LABS', 'STUDIO',
        'INDUSTRIES', 'INTERNATIONAL', 'GLOBAL', 'AND',
    ];

    private const array VOWELS = ['A', 'E', 'I', 'O', 'U'];

    private const string PADDING = 'X';

    private const int MIN_LENGTH = 4;

    private const int MAX_LENGTH = 6;

    /** The full ranked, de-duplicated, reserved-filtered candidate list, best first. */
    public static function candidates(string $legalName): AliasCandidates
    {
        $ranked = [];
        $seen = [];

        $push = static function (string $code) use (&$ranked, &$seen): void {
            $code = strtoupper($code);

            if ($code === '' || isset($seen[$code])) {
                return;
            }

            if (preg_match('/^[A-Z][A-Z0-9]{3,5}$/', $code) !== 1 || self::isReserved($code)) {
                return;
            }

            $seen[$code] = true;
            $ranked[] = $code;
        };

        // Widen: every 4-char candidate, then 5, then 6.
        for ($length = self::MIN_LENGTH; $length <= self::MAX_LENGTH; $length++) {
            foreach (self::deriveForLength($legalName, $length) as $candidate) {
                $push($candidate);
            }
        }

        // Only then a numeric discriminator (ACME2), the last resort.
        $base = self::deriveForLength($legalName, self::MIN_LENGTH)[0] ?? null;

        if ($base !== null) {
            for ($n = 2; $n <= 99; $n++) {
                $push(substr($base, 0, 3) . $n);
            }
        }

        return new AliasCandidates($legalName, $ranked);
    }

    public static function isReserved(string $alias): bool
    {
        return in_array(strtoupper($alias), self::RESERVED, true);
    }

    /** @return list<string> */
    public static function reserved(): array
    {
        return self::RESERVED;
    }

    /**
     * Ranked candidates of exactly one length: head + distinctive tail, truncation, initials, consonant
     * skeleton, a re-admitted generic word, then first-two + last-two.
     *
     * @return list<string>
     */
    private static function deriveForLength(string $name, int $length): array
    {
        [$core, $all] = self::normalise($name);

        if ($core === []) {
            return [];
        }

        $out = [];
        $push = static function (string $code) use (&$out, $length): void {
            if ($code === '') {
                return;
            }

            $code = str_pad(substr($code, 0, $length), $length, self::PADDING);

            if (ctype_alpha($code[0]) && !in_array($code, $out, true)) {
                $out[] = $code;
            }
        };

        $joined = implode('', $core);
        $joinedAll = implode('', $all);

        // Head + distinctive tail: turns AdelsaIQ into ADIQ rather than the flatter ADEL.
        if (strlen($joined) >= $length) {
            $push(substr($joined, 0, 2) . substr($joined, -($length - 2)));
        }

        $push($joined);

        if (count($core) > 1) {
            $push(implode('', array_map(static fn (string $w): string => $w[0], $core)) . substr($core[0], 1));
        }

        $push($joined[0] . str_replace(self::VOWELS, '', substr($joined, 1)));

        // Re-admit a generic word purely to break a tie (Northwind Traders vs Technologies).
        if (count($all) > count($core)) {
            $push(implode('', array_map(static fn (string $w): string => $w[0], $all)) . substr($all[0], 1));
            $push($joinedAll);
        }

        if (strlen($joined) >= 4) {
            $push(substr($joined, 0, 2) . substr($joined, -2));
        }

        return $out;
    }

    /** @return array{0: list<string>, 1: list<string>} core words (distinctive), all words */
    private static function normalise(string $name): array
    {
        $value = strtoupper(self::transliterate($name));
        $value = str_replace('&', ' AND ', $value);
        $value = preg_replace('/[^A-Z0-9 ]+/', ' ', $value) ?? '';

        $words = array_values(array_filter(explode(' ', $value), static fn (string $w): bool => $w !== ''));

        while ($words !== [] && in_array(end($words), self::LEGAL_SUFFIXES, true)) {
            array_pop($words);
        }

        $core = array_values(array_filter(
            $words,
            static fn (string $w): bool => !in_array($w, self::GENERIC_WORDS, true),
        ));

        return [$core === [] ? $words : $core, $words];
    }

    /** Fold accents, so "Café" and "Cafe" never yield two codes for one client. */
    private static function transliterate(string $value): string
    {
        if (function_exists('transliterator_transliterate')) {
            $result = transliterator_transliterate('Any-Latin; Latin-ASCII', $value);

            if (is_string($result)) {
                return $result;
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', 'ASCII//TRANSLIT', $value);

            if ($converted !== false) {
                return $converted;
            }
        }

        return $value;
    }
}
