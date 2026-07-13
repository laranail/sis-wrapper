<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decider;

use Simtabi\SIS\Command\AttachSubject;
use Simtabi\SIS\Decision\AppendAudit;
use Simtabi\SIS\Decision\Decision;
use Simtabi\SIS\Decision\SetSubject;
use Simtabi\SIS\Event\SubjectAttached;
use Simtabi\SIS\Exception\IllegalTransitionException;
use Simtabi\SIS\Identifier\LifecycleState;
use Simtabi\SIS\Policy\SubjectPolicy;
use Simtabi\SIS\Snapshot\AttachSubjectSnapshot;

/**
 * Attach the subject to a still-reserved identifier. Once commissioned the subject is frozen (§9), so
 * this is legal only in Reserved.
 */
final class AttachSubjectDecider
{
    #[\NoDiscard('the returned Decision must be applied by the registrar')]
    public static function decide(AttachSubject $command, AttachSubjectSnapshot $snapshot): Decision
    {
        $id = $command->identifier;

        if ($snapshot->currentState !== LifecycleState::Reserved) {
            throw IllegalTransitionException::between((string) $id, $snapshot->currentState->value, 'subject-attached');
        }

        SubjectPolicy::assertUnnamed($command->subject, $snapshot->subjectAlreadyNamed);

        return new Decision(
            [
                new SetSubject($id, $command->subject, $command->occurredAt),
                new AppendAudit(
                    identifier: (string) $id,
                    action: 'attach-subject',
                    actor: $command->actor,
                    before: $snapshot->currentState->value,
                    after: $snapshot->currentState->value,
                    correlationId: $command->correlationId,
                    idempotencyKey: $command->idempotencyKey,
                    at: $command->occurredAt,
                    context: ['subject' => $command->subject->reference()],
                ),
            ],
            [
                new SubjectAttached((string) $id, $command->occurredAt, $command->correlationId, $command->subject->type, $command->subject->id),
            ],
        );
    }
}
