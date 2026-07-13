<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\SIS\Command\VoidIdentifier;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Void a RESERVED identifier that will never be used (§6.1). A commissioned identifier can never be voided;
 * the decider enforces it.
 */
final class VoidReservation
{
    public function __construct(
        private readonly Registrar $registrar,
    ) {}

    public function __invoke(Identifier $identifier, string $reason, CommandContext $context): void
    {
        $this->registrar->apply(new VoidIdentifier(
            $identifier,
            $reason,
            $context->actor,
            $context->occurredAt,
            $context->correlationId,
            $context->idempotencyKey,
        ));
    }
}
