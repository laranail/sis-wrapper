<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

use Simtabi\SIS\Contract\SisException;
use Simtabi\SIS\Exception\CheckCharacterMismatchException;
use Simtabi\SIS\Exception\ExhaustedSerialSpaceException;
use Simtabi\SIS\Exception\MalformedIdentifierException;
use Simtabi\SIS\Exception\ScopeMismatchException;
use Simtabi\SIS\Exception\UnknownIdClassException;
use Simtabi\SIS\Support\CheckCharacters;
use Stringable;

/**
 * A SIS/1 identifier — SIM-STD-0001:2026 §2.
 *
 *   Form G (global):  SIM-{CLASS}-{SERIAL}-{CHECK}          SIM-PRS-100001-FA
 *   Form S (scoped):  SIM-{CLASS}-{SCOPE}-{SERIAL}-{CHECK}  SIM-INV-ADIQ-000001-VY
 *
 * Every segment is immutable. There is no mutable portion — state, ownership, description, and price
 * live in the register, never in the identifier.
 */
final readonly class Identifier implements Stringable
{
    public const string ISSUER = 'SIM';

    private const string FORM_G = '/^SIM-([A-Z]{3})-(\d{6,9})-([0-9A-Z]{2})$/';

    private const string FORM_S = '/^SIM-([A-Z]{3})-([A-Z][A-Z0-9]{3,5})-(\d{6,9})-([0-9A-Z]{2})$/';

    private function __construct(
        public IdClass $class,
        public ?string $scope,
        public int $serial,
        public string $check,
        public string $value,
    ) {}

    /** Mint an identifier from its parts. The check characters are derived, never supplied. */
    #[\NoDiscard('minting allocates an identifier; the returned value is the only copy')]
    public static function mint(IdClass $class, int $serial, ?string $scope = null, int $width = 6): self
    {
        if ($class->isScoped() !== ($scope !== null)) {
            throw $class->isScoped()
                ? ScopeMismatchException::scopeRequired($class->value)
                : ScopeMismatchException::scopeForbidden($class->value);
        }

        $serialValue = new Serial($serial);
        $padded = $serialValue->padded($width);      // validates 6 <= width <= 9

        if (!$serialValue->fitsWidth($width)) {
            throw ExhaustedSerialSpaceException::of($class->value, $scope, $width);
        }

        $normalisedScope = $scope === null ? null : Scope::of($scope)->value;

        $core = $normalisedScope === null
            ? sprintf('%s-%s-%s', self::ISSUER, $class->value, $padded)
            : sprintf('%s-%s-%s-%s', self::ISSUER, $class->value, $normalisedScope, $padded);

        return self::parse($core . '-' . CheckCharacters::for($core));
    }

    /**
     * Parse and validate. Rejects anything malformed, anything whose class is unknown, anything whose
     * scope does not match its class, and anything whose check characters do not verify.
     */
    public static function parse(string $value): self
    {
        $value = strtoupper(trim($value));

        if (preg_match(self::FORM_S, $value, $m) === 1) {
            [, $class, $scope, $serial, $check] = $m;
        } elseif (preg_match(self::FORM_G, $value, $m) === 1) {
            [, $class, $serial, $check] = $m;
            $scope = null;
        } else {
            throw MalformedIdentifierException::of($value);
        }

        $idClass = IdClass::tryFrom($class);

        if ($idClass === null) {
            throw UnknownIdClassException::code($class);
        }

        if ($idClass->isScoped() !== ($scope !== null)) {
            throw $idClass->isScoped()
                ? ScopeMismatchException::scopeRequired($class)
                : ScopeMismatchException::scopeForbidden($class);
        }

        $core = substr($value, 0, (int) strrpos($value, '-'));
        $expected = CheckCharacters::for($core);

        if (!CheckCharacters::verify($core, $check)) {
            throw CheckCharacterMismatchException::of($value, $expected, $check);
        }

        return new self($idClass, $scope, (int) $serial, $check, $value);
    }

    /** True if $value is a well-formed, check-valid SIS/1 identifier. */
    public static function isValid(string $value): bool
    {
        try {
            self::parse($value);

            return true;
        } catch (SisException) {
            return false;
        }
    }

    /** What kind of thing is this? Null if it is not a SIS/1 identifier at all. */
    public static function classify(string $value): ?IdClass
    {
        try {
            return self::parse($value)->class;
        } catch (SisException) {
            return null;
        }
    }

    /** The identifier without its check characters. */
    public function core(): string
    {
        $position = strrpos($this->value, '-');

        return $position === false ? $this->value : substr($this->value, 0, $position);
    }

    public function isScoped(): bool
    {
        return $this->scope !== null;
    }

    public function is(IdClass $class): bool
    {
        return $this->class === $class;
    }

    /** Comparison ignores case and separators, per §2.4. */
    public function equals(self $other): bool
    {
        return $this->comparable() === $other->comparable();
    }

    public function comparable(): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->value) ?? '');
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
