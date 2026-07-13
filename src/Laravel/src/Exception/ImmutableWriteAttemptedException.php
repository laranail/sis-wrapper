<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisStateException;
use Throwable;

/**
 * The immutability trigger fired: something tried to UPDATE a locked row and the storage layer stopped it.
 * A hit here means a bug in us or a person at a psql prompt — a SECURITY event, not noise. The shell logs
 * it at error, notifies a human, and records the attempt.
 */
final class ImmutableWriteAttemptedException extends SisStateException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §6.4';

    public static function of(?Throwable $previous = null): self
    {
        return new self(
            'A locked register row was modified in the database and the immutability trigger rejected it '
            . '(SIM-STD-0001:2026 §6.4). This is a security event.',
            ['operation' => 'update', 'component' => 'immutability-trigger'],
            $previous,
        );
    }
}
