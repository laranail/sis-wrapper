<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\AssignAlias;
use Simtabi\SIS\Decision\ChangeState;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\SetSubject;
use Simtabi\SIS\Event\AliasAssigned;
use Simtabi\SIS\Event\IdentifierCommissioned;
use Simtabi\SIS\Event\SubjectAttached;
use Simtabi\SIS\Exception\AliasTakenException;
use Simtabi\SIS\Exception\AlreadyCommissionedException;
use Simtabi\SIS\Exception\IllegalTransitionException;
use Simtabi\SIS\Exception\ReservedAliasException;
use Simtabi\SIS\Exception\SubjectAlreadyNamedException;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Policy\AliasPolicy;
use Simtabi\SIS\Snapshot\CommissionSnapshot;

/** Move a reserved identifier into service, optionally assigning its alias and subject, and lock it. */
final class CommissionDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(Commission $command, CommissionSnapshot $snapshot): Decision
    {
        $id = $command->identifier;
        $state = $snapshot->currentState;

        if ($state === LifecycleState::Commissioned) {
            throw AlreadyCommissionedException::of((string) $id);
        }

        if ($state !== LifecycleState::Reserved) {
            throw IllegalTransitionException::between((string) $id, $state->value, LifecycleState::Commissioned->value);
        }

        if ($command->alias !== null) {
            if (AliasPolicy::isReserved($command->alias->value)) {
                throw ReservedAliasException::of($command->alias->value);
            }

            if ($snapshot->aliasTaken) {
                throw AliasTakenException::of($command->alias->value);
            }
        }

        if ($command->subject !== null && $snapshot->subjectAlreadyNamed) {
            throw SubjectAlreadyNamedException::of($command->subject->type, $command->subject->id);
        }

        $effects = [new ChangeState($id, $state, LifecycleState::Commissioned, $command->occurredAt)];
        $events = [new IdentifierCommissioned((string) $id, $command->occurredAt, $command->correlationId, $command->alias?->value)];

        if ($command->alias !== null) {
            $effects[] = new AssignAlias($id, $command->alias, $command->occurredAt);
            $events[] = new AliasAssigned((string) $id, $command->occurredAt, $command->correlationId, $command->alias->value);
        }

        if ($command->subject !== null) {
            $effects[] = new SetSubject($id, $command->subject, $command->occurredAt);
            $events[] = new SubjectAttached((string) $id, $command->occurredAt, $command->correlationId, $command->subject->type, $command->subject->id);
        }

        $effects[] = new AppendAudit(
            identifier: (string) $id,
            action: 'commission',
            actor: $command->actor,
            before: LifecycleState::Reserved->value,
            after: LifecycleState::Commissioned->value,
            correlationId: $command->correlationId,
            idempotencyKey: $command->idempotencyKey,
            at: $command->occurredAt,
        );

        return new Decision($effects, $events);
    }
}
