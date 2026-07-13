<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\SIS\Command\AttachSubject as AttachSubjectCommand;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * Attach the polymorphic subject to a still-reserved identifier (§5, §9). Once commissioned the subject is
 * frozen; the decider enforces "one thing, one identifier."
 */
final class AttachSubject
{
    public function __construct(
        private readonly Registrar $registrar,
    ) {}

    public function __invoke(Identifier $identifier, SubjectRef $subject, CommandContext $context): Identifier
    {
        $this->registrar->apply(new AttachSubjectCommand(
            $identifier,
            $subject,
            $context->actor,
            $context->occurredAt,
            $context->correlationId,
            $context->idempotencyKey,
        ));

        return $identifier;
    }
}
