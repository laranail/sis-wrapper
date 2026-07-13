<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\SIS\Command\Supersede;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Record that an identifier has been superseded by a successor (§8) — never editing the superseded one.
 * The decider detects cycles and requires the successor to exist.
 */
final class SupersedeIdentifier
{
    public function __construct(
        private readonly Registrar $registrar,
    ) {}

    public function __invoke(Identifier $identifier, Identifier $successor, CommandContext $context): Identifier
    {
        $this->registrar->apply(new Supersede(
            $identifier,
            $successor,
            $context->actor,
            $context->occurredAt,
            $context->correlationId,
            $context->idempotencyKey,
        ));

        return $successor;
    }
}
