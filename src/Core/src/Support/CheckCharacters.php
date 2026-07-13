<?php

declare(strict_types=1);

namespace Simtabi\SIS\Support;

use Simtabi\SIS\Exception\IllegalCharacterException;

/**
 * ISO 7064 MOD 1271-36 — the pure double-character check, per SIM-STD-0001:2026 §4.
 *
 * The single-character alternative (MOD 37,36) was measured and rejected: it fails on adjacent
 * transpositions, and SIM-PRS-100001 / SIM-PRS-100010 share a check character under it. MOD 97-10
 * (IBAN) was rejected too — it misses 27 of 5,320 substitutions. MOD 1271-36 catches 100% of
 * single-character substitution, adjacent transposition, jump transposition, and twin errors. Those
 * four claims are proven by the property-based test in the suite; do not re-propose the rejected
 * algorithms (§4.2).
 */
final class CheckCharacters
{
    private const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    private const int MODULUS = 1271;

    private const int RADIX = 36;

    /** The two check characters for a payload. */
    public static function for(string $payload): string
    {
        $payload = self::normalise($payload);
        $p = 0;

        foreach (str_split($payload) as $char) {
            $value = strpos(self::ALPHABET, $char);

            if ($value === false) {
                throw IllegalCharacterException::of($char);
            }

            $p = (($p + $value) * self::RADIX) % self::MODULUS;
        }

        // Second shift: this is what makes it a two-character check.
        $p = ($p * self::RADIX) % self::MODULUS;

        $check = (self::MODULUS + 1 - $p) % self::MODULUS;

        return self::ALPHABET[intdiv($check, self::RADIX)] . self::ALPHABET[$check % self::RADIX];
    }

    /** Does $check correctly check $payload? Compared in constant time. */
    public static function verify(string $payload, string $check): bool
    {
        return hash_equals(self::for($payload), strtoupper($check));
    }

    /** Separators and case are irrelevant to the check; only the payload matters (§2.4). */
    private static function normalise(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $value) ?? '');
    }
}
