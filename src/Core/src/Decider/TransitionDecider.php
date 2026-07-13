<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\Transition;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\ChangeState;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Event\IdentifierTransitioned;
use Simtabi\SIS\Policy\TransitionPolicy;
use Simtabi\SIS\Snapshot\TransitionSnapshot;

/** Suspend, restore, or decommission — every legality check goes through the state machine (§6.2). */
final class TransitionDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(Transition $command, TransitionSnapshot $snapshot): Decision
    {
        $id = $command->identifier;
        $from = $snapshot->currentState;
        $to = $command->to;

        TransitionPolicy::assertLegal((string) $id, $from, $to);

        return new Decision(
            [
                new ChangeState($id, $from, $to, $command->occurredAt),
                new AppendAudit(
                    identifier: (string) $id,
                    action: 'transition:' . $to->value,
                    actor: $command->actor,
                    before: $from->value,
                    after: $to->value,
                    correlationId: $command->correlationId,
                    idempotencyKey: $command->idempotencyKey,
                    at: $command->occurredAt,
                ),
            ],
            [
                new IdentifierTransitioned((string) $id, $command->occurredAt, $command->correlationId, $from, $to),
            ],
        );
    }
}
