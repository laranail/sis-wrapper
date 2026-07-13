<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\AttachSubject as AttachSubjectCommand;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * Attach the polymorphic subject to a still-reserved identifier (§5, §9). Once commissioned the subject is
 * frozen; the decider enforces "one thing, one identifier." Wrapped in idempotency keyed on (identifier,
 * subject), so a retried attach replays rather than re-applying.
 */
final class AttachSubject
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function __invoke(Identifier $identifier, SubjectRef $subject, CommandContext $context): Identifier
    {
        $requestHash = hash('sha256', json_encode(['attach-subject', (string) $identifier, $subject->type, $subject->id], JSON_THROW_ON_ERROR));

        return $this->idempotency->rememberIdentifier($context->actor, $context->idempotencyKey, $requestHash, function () use ($identifier, $subject, $context): Identifier {
            $this->registrar->apply(new AttachSubjectCommand(
                $identifier,
                $subject,
                $context->actor,
                $context->occurredAt,
                $context->correlationId,
                $context->idempotencyKey,
            ));

            return $identifier;
        });
    }
}
