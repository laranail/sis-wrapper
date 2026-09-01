<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisExceptionBase;
use Throwable;

/**
 * The outbox relay failed. Degradable (Part II): the write committed and nothing is corrupted — the relay
 * retries. This never rolls back a commissioning that already happened.
 */
final class OutboxRelayException extends SisExceptionBase
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2.7 (outbox)';

    protected const string CRITICALITY = 'degradable';

    public static function of(int $outboxId, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Relaying outbox message #%d failed; it will be retried.', $outboxId),
            ['operation' => 'relay-outbox', 'outbox_id' => $outboxId],
            $previous,
        );
    }
}
