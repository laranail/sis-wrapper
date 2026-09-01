<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Record that an identifier has been superseded by a successor (§8) — never editing the superseded one.
 * The decider detects cycles and requires the successor to exist. Wrapped in idempotency keyed on
 * (identifier, successor), so a retried supersession replays rather than failing as already-superseded.
 */
final class SupersedeIdentifier
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function __invoke(Identifier $identifier, Identifier $successor, CommandContext $context): Identifier
    {
        $requestHash = hash('sha256', json_encode(['supersede', (string) $identifier, (string) $successor], JSON_THROW_ON_ERROR));

        return $this->idempotency->rememberIdentifier($context->actor, $context->idempotencyKey, $requestHash, function () use ($identifier, $successor, $context): Identifier {
            $this->registrar->apply(new Supersede(
                $identifier,
                $successor,
                $context->actor,
                $context->occurredAt,
                $context->correlationId,
                $context->idempotencyKey,
            ));

            return $successor;
        });
    }
}
