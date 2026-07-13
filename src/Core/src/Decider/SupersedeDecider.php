<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\SetSupersededBy;
use Simtabi\SIS\Event\IdentifierSuperseded;
use Simtabi\SIS\Exception\UnknownIdentifierException;
use Simtabi\SIS\Policy\SupersessionPolicy;
use Simtabi\SIS\Snapshot\SupersedeSnapshot;

/** Record a supersession pointer without editing the superseded identifier — the chain is the audit trail. */
final class SupersedeDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(Supersede $command, SupersedeSnapshot $snapshot): Decision
    {
        $id = $command->identifier;
        $successor = $command->successor;

        if (!$snapshot->successorExists) {
            throw UnknownIdentifierException::of((string) $successor);
        }

        SupersessionPolicy::assertNoCycle($id->comparable(), $successor->comparable(), $snapshot->successorChain);

        return new Decision(
            [
                new SetSupersededBy($id, $successor, $command->occurredAt),
                new AppendAudit(
                    identifier: (string) $id,
                    action: 'supersede',
                    actor: $command->actor,
                    before: $snapshot->currentState->value,
                    after: $snapshot->currentState->value,
                    correlationId: $command->correlationId,
                    idempotencyKey: $command->idempotencyKey,
                    at: $command->occurredAt,
                    context: ['successor' => (string) $successor],
                ),
            ],
            [
                new IdentifierSuperseded((string) $id, $command->occurredAt, $command->correlationId, (string) $successor),
            ],
        );
    }
}
