<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Psr\Log\LoggerInterface;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Outbox\OutboxRelay;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Decision\Decision;
use Throwable;

/**
 * After the write commits (this sits outside the transaction), relays the outbox eagerly for lower
 * latency. Relay failure is DEGRADABLE: the write is safe and durable, and the scheduled RelayOutbox job
 * drains anything left behind — so a relay error is reported, not raised.
 */
final class OutboxRelayingRegistrar implements Registrar
{
    public function __construct(
        private readonly Registrar $inner,
        private readonly OutboxRelay $relay,
        private readonly LoggerInterface $logger,
    ) {}

    public function apply(Command $command): Decision
    {
        $decision = $this->inner->apply($command);

        try {
            $this->relay->relayPending();
        } catch (Throwable $e) {
            $this->logger->warning('sis.outbox.relay_deferred', [
                'correlation_id' => $command->correlationId(),
                'error' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }

        return $decision;
    }
}
