<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\Release;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\DeleteRecord;
use Simtabi\SIS\Event\IdentifierReleased;
use Simtabi\SIS\Exception\CannotReleaseCommissionedException;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Snapshot\ReleaseSnapshot;

/** Return a reserved identifier to the pool. The single most important guard: only Reserved is releasable. */
final class ReleaseDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(Release $command, ReleaseSnapshot $snapshot): Decision
    {
        $id = $command->identifier;

        if (!$snapshot->currentState->isReleasable()) {
            throw CannotReleaseCommissionedException::of((string) $id, $snapshot->currentState->value);
        }

        return new Decision(
            [
                new DeleteRecord($id, $command->occurredAt),
                new AppendAudit(
                    identifier: (string) $id,
                    action: 'release',
                    actor: $command->actor,
                    before: LifecycleState::Reserved->value,
                    after: null,
                    correlationId: $command->correlationId,
                    idempotencyKey: $command->idempotencyKey,
                    at: $command->occurredAt,
                ),
            ],
            [
                new IdentifierReleased((string) $id, $command->occurredAt, $command->correlationId),
            ],
        );
    }
}
