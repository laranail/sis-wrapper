<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisConflictException;

/**
 * An idempotency key was reused with a DIFFERENT payload. The client is confused; do not guess. Rendered
 * 422. The key is scoped to (actor, key), never key alone — a global namespace is a cross-tenant replay.
 */
final class IdempotencyConflictException extends SisConflictException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2.11 (idempotency)';

    public static function of(string $key): self
    {
        return new self(
            sprintf('Idempotency key "%s" was reused with a different request payload.', $key),
            ['operation' => 'idempotency', 'idempotency_key' => $key],
        );
    }

    /**
     * The key is claimed but its first request has not committed — a concurrent in-flight request, or a
     * crashed one. Never act again under a claimed key; the client should retry once the first completes.
     */
    public static function inProgress(string $key): self
    {
        return new self(
            sprintf('Idempotency key "%s" is already in progress; retry once the first request completes.', $key),
            ['operation' => 'idempotency', 'idempotency_key' => $key],
        );
    }
}
