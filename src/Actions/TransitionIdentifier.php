<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Apply a lifecycle transition (§6.2). The named methods are the vocabulary a caller uses; every one funnels
 * into the one Command builder, and the registrar authorizes and audits each. Wrapped in idempotency keyed
 * on (identifier, target state), so a retried transition replays rather than re-applying — a second attempt
 * would otherwise be rejected as an illegal same-state transition.
 */
final class TransitionIdentifier
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function to(Identifier $identifier, LifecycleState $to, CommandContext $context): Identifier
    {
        $requestHash = hash('sha256', json_encode(['transition', (string) $identifier, $to->value], JSON_THROW_ON_ERROR));

        return $this->idempotency->rememberIdentifier($context->actor, $context->idempotencyKey, $requestHash, function () use ($identifier, $to, $context): Identifier {
            $this->registrar->apply(new Transition(
                $identifier,
                $to,
                $context->actor,
                $context->occurredAt,
                $context->correlationId,
                $context->idempotencyKey,
            ));

            return $identifier;
        });
    }

    public function suspend(Identifier $identifier, CommandContext $context): Identifier
    {
        return $this->to($identifier, LifecycleState::Suspended, $context);
    }

    public function restore(Identifier $identifier, CommandContext $context): Identifier
    {
        return $this->to($identifier, LifecycleState::Commissioned, $context);
    }

    public function decommission(Identifier $identifier, CommandContext $context): Identifier
    {
        return $this->to($identifier, LifecycleState::Decommissioned, $context);
    }
}
