<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisExceptionBase;

/**
 * A boot-time misconfiguration that makes the package unsafe to run. Critical in every environment
 * (Part II): the morph map is not enforced, or scheduling is on with a lock driver that cannot serialise
 * across servers. Boot fails loudly rather than running a subtly-wrong system.
 */
final class SisBootException extends SisExceptionBase
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §6.4';

    public static function morphMapNotEnforced(): self
    {
        return new self(
            'Relation::requireMorphMap() is not enforced, but the SIS registrar/API is enabled. A raw class '
            . 'name written into an immutable register row is a time bomb. Enforcement is non-negotiable.',
            ['operation' => 'boot', 'component' => 'morph'],
        );
    }

    public static function incompatibleScheduleLock(string $driver): self
    {
        return new self(
            sprintf(
                'SIS scheduling is enabled with the "%s" cache lock driver, which cannot serialise across '
                . 'servers. onOneServer() needs redis, database, or memcached, or the sweeps run on every server.',
                $driver,
            ),
            ['operation' => 'boot', 'component' => 'schedule', 'driver' => $driver],
        );
    }
}
