<?php

declare(strict_types=1);

namespace Simtabi\SIS\Version;

use Simtabi\SIS\Exception\InvalidVersionException;
use Stringable;

/**
 * A release version — SIM-STD-0001:2026 §7.2.
 *
 *   {PRODUCT_ALIAS}-{MAJOR}.{MINOR}.{PATCH}[-{PRERELEASE}][+{BUILD}]
 *   MALISA-1.4.2
 *   MALISA-2.0.0-rc.1
 *   MALISA-1.4.3+20260712.a91f2c
 *
 * Precedence follows Semantic Versioning 2.0.0 §11: build metadata is ignored for ordering, a
 * pre-release sorts below its release, and pre-release identifiers are compared field by field —
 * numeric fields numerically, numeric ranking below alphanumeric, and a larger set of fields winning
 * when all preceding fields are equal.
 *
 * A published release is immutable. A defect is fixed by shipping a NEW release, never by amending an
 * old one — the chain of supersession is the audit trail.
 */
final readonly class Version implements Stringable
{
    private const string SEMVER = '/^(?<major>0|[1-9]\d*)\.(?<minor>0|[1-9]\d*)\.(?<patch>0|[1-9]\d*)'
        . '(?:-(?<pre>[0-9A-Za-z.-]+))?(?:\+(?<build>[0-9A-Za-z.-]+))?$/';

    private const string PRODUCT = '/^[A-Z][A-Z0-9]{3,5}$/';

    public function __construct(
        public string $product,
        public int $major,
        public int $minor,
        public int $patch,
        public ?string $preRelease = null,
        public ?string $build = null,
    ) {}

    public static function parse(string $value): self
    {
        $value = trim($value);
        $dash = strpos($value, '-');

        if ($dash === false) {
            throw InvalidVersionException::of($value);
        }

        $product = strtoupper(substr($value, 0, $dash));
        $semver = substr($value, $dash + 1);

        if (preg_match(self::PRODUCT, $product) !== 1 || preg_match(self::SEMVER, $semver, $m) !== 1) {
            throw InvalidVersionException::of($value);
        }

        return new self(
            product: $product,
            major: (int) $m['major'],
            minor: (int) $m['minor'],
            patch: (int) $m['patch'],
            preRelease: ($m['pre'] ?? '') !== '' ? $m['pre'] : null,
            build: ($m['build'] ?? '') !== '' ? $m['build'] : null,
        );
    }

    public function isPreRelease(): bool
    {
        return $this->preRelease !== null;
    }

    /**
     * Negative if $this precedes $other, per semver 2.0.0 §11. Build metadata is ignored. Comparing
     * versions of different products is a programming error, not a silent ordering.
     */
    public function compare(self $other): int
    {
        if ($this->product !== $other->product) {
            throw InvalidVersionException::productMismatch($this->product, $other->product);
        }

        foreach (['major', 'minor', 'patch'] as $field) {
            if ($this->{$field} !== $other->{$field}) {
                return $this->{$field} <=> $other->{$field};
            }
        }

        // A pre-release sorts BELOW its release: 1.0.0-rc.1 < 1.0.0.
        if ($this->preRelease === null && $other->preRelease === null) {
            return 0;
        }

        if ($this->preRelease === null) {
            return 1;
        }

        if ($other->preRelease === null) {
            return -1;
        }

        return self::comparePreRelease($this->preRelease, $other->preRelease);
    }

    public function precedes(self $other): bool
    {
        return $this->compare($other) < 0;
    }

    /** Semver 2.0.0 §11 pre-release precedence, field by field. */
    private static function comparePreRelease(string $a, string $b): int
    {
        $left = explode('.', $a);
        $right = explode('.', $b);
        $shared = min(count($left), count($right));

        for ($i = 0; $i < $shared; $i++) {
            $x = $left[$i];
            $y = $right[$i];
            $xNumeric = ctype_digit($x);
            $yNumeric = ctype_digit($y);

            $cmp = match (true) {
                $xNumeric && $yNumeric => (int) $x <=> (int) $y,
                $xNumeric => -1,             // numeric identifiers rank below alphanumeric
                $yNumeric => 1,
                default => strcmp($x, $y),
            };

            if ($cmp !== 0) {
                return $cmp <=> 0;
            }
        }

        // All shared fields equal: the version with more fields has higher precedence.
        return count($left) <=> count($right);
    }

    public function __toString(): string
    {
        $out = sprintf('%s-%d.%d.%d', $this->product, $this->major, $this->minor, $this->patch);

        if ($this->preRelease !== null) {
            $out .= '-' . $this->preRelease;
        }

        if ($this->build !== null) {
            $out .= '+' . $this->build;
        }

        return $out;
    }
}
