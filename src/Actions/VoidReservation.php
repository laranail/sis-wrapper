<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Void a RESERVED identifier that will never be used (§6.1). A commissioned identifier can never be voided;
 * the decider enforces it. Wrapped in idempotency keyed on (identifier, reason), so a retried void replays
 * rather than failing as already-voided.
 */
final class VoidReservation
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function __invoke(Identifier $identifier, string $reason, CommandContext $context): void
    {
        $requestHash = hash('sha256', json_encode(['void', (string) $identifier, $reason], JSON_THROW_ON_ERROR));

        $this->idempotency->rememberIdentifier($context->actor, $context->idempotencyKey, $requestHash, function () use ($identifier, $reason, $context): Identifier {
            $this->registrar->apply(new VoidIdentifier(
                $identifier,
                $reason,
                $context->actor,
                $context->occurredAt,
                $context->correlationId,
                $context->idempotencyKey,
            ));

            return $identifier;
        });
    }
}
