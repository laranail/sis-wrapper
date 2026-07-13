<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\InsertRecord;
use Simtabi\SIS\Event\IdentifierReserved;
use Simtabi\SIS\Exception\SerialCollisionException;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Snapshot\ReserveSnapshot;

/** Reserve is the create path: it inserts a RESERVED row, the only state that can later be released. */
final class ReserveDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(Reserve $command, ReserveSnapshot $snapshot): Decision
    {
        $id = $command->identifier;

        // Advisory precondition; the unique index on (class, scope, serial) is the authority.
        if ($snapshot->identifierExists) {
            throw SerialCollisionException::of($id->class->value, $id->scope, $id->serial);
        }

        return new Decision(
            [
                new InsertRecord(
                    identifier: $id,
                    state: LifecycleState::Reserved,
                    specEdition: $command->edition(),
                    reservedAt: $command->occurredAt,
                    reservedReason: $command->reason,
                    reservedBy: $command->reservedBy,
                    expiresAt: $command->expiresAt,
                ),
                new AppendAudit(
                    identifier: (string) $id,
                    action: 'reserve',
                    actor: $command->actor,
                    before: null,
                    after: LifecycleState::Reserved->value,
                    correlationId: $command->correlationId,
                    idempotencyKey: $command->idempotencyKey,
                    at: $command->occurredAt,
                ),
            ],
            [
                new IdentifierReserved((string) $id, $command->occurredAt, $command->correlationId, $command->reason),
            ],
        );
    }
}
