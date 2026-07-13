<?php

declare(strict_types=1);

namespace Simtabi\SIS;

use Simtabi\SIS\Command\Minter;
use Simtabi\SIS\Identifier\AliasCandidates;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Policy\AliasPolicy;
use Simtabi\SIS\Version\Version;

/**
 * SIS/1 — the pure, framework-free entry point. The reference implementation of SIM-STD-0001:2026.
 *
 * Everything here is a total function over immutable values. It builds commands and answers questions; it
 * never persists, reads a clock, logs, or dispatches. The shell applies the commands it produces.
 *
 *   $reserve = Sis::mint(IdClass::Person)->withSerial(100001)->by($actor)->at($now)
 *                  ->correlatedBy($cid)->idempotentWith($key)->reserve('new hire');
 *
 *   Sis::validate('SIM-INV-ADIQ-000001-VY');   // true
 *   Sis::identify('SIM-PRS-100001-FA');        // IdClass::Person
 *   Sis::aliasCandidates('AdelsaIQ LLC');      // ranked: ADIQ, ADEL, ...
 */
final class Sis
{
    public const string SPECIFICATION = 'SIM-STD-0001:2026';

    public const string EDITION = 'SIS/1';

    /** Begin building a command for an identifier of the given class. */
    #[\NoDiscard('the minter builds a command that must be dispatched')]
    public static function mint(IdClass $class): Minter
    {
        return new Minter($class);
    }

    public static function validate(string $value): bool
    {
        return Identifier::isValid($value);
    }

    public static function identify(string $value): ?IdClass
    {
        return Identifier::classify($value);
    }

    public static function parse(string $value): Identifier
    {
        return Identifier::parse($value);
    }

    /** Ranked alias candidates for a legal name, so a human can choose (§5.2). */
    public static function aliasCandidates(string $legalName): AliasCandidates
    {
        return AliasPolicy::candidates($legalName);
    }

    /** Parse a release version (§7.2). Pure — minting a REL identifier is an Action in the shell. */
    public static function version(string $value): Version
    {
        return Version::parse($value);
    }
}
