<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\ChangeState;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Event\IdentifierVoided;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Policy\TransitionPolicy;
use Simtabi\SIS\Snapshot\VoidSnapshot;

/** Void a reserved-and-never-used identifier. Only Reserved -> Void is legal; a commissioned id can never be voided. */
final class VoidDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(VoidIdentifier $command, VoidSnapshot $snapshot): Decision
    {
        $id = $command->identifier;
        $from = $snapshot->currentState;

        TransitionPolicy::assertLegal((string) $id, $from, LifecycleState::Void);

        return new Decision(
            [
                new ChangeState($id, $from, LifecycleState::Void, $command->occurredAt),
                new AppendAudit(
                    identifier: (string) $id,
                    action: 'void',
                    actor: $command->actor,
                    before: $from->value,
                    after: LifecycleState::Void->value,
                    correlationId: $command->correlationId,
                    idempotencyKey: $command->idempotencyKey,
                    at: $command->occurredAt,
                    context: ['reason' => $command->reason],
                ),
            ],
            [
                new IdentifierVoided((string) $id, $command->occurredAt, $command->correlationId, $command->reason),
            ],
        );
    }
}
