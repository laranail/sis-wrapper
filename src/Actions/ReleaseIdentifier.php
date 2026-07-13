<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Return a RESERVED identifier to the pool. The decider refuses any other state — the single most
 * important guard. The registrar authorizes (`sis.identifier.release`) and audits. Wrapped in idempotency
 * keyed on the identifier, so a retried release replays rather than failing as already-released.
 */
final class ReleaseIdentifier
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function __invoke(Identifier $identifier, CommandContext $context): void
    {
        $requestHash = hash('sha256', json_encode(['release', (string) $identifier], JSON_THROW_ON_ERROR));

        $this->idempotency->rememberIdentifier($context->actor, $context->idempotencyKey, $requestHash, function () use ($identifier, $context): Identifier {
            $this->registrar->apply(new Release(
                $identifier,
                $context->actor,
                $context->occurredAt,
                $context->correlationId,
                $context->idempotencyKey,
            ));

            return $identifier;
        });
    }
}
