<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Data\CommissionData;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\Commission;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Commission a reserved identifier and lock it forever. Authorization, transaction, audit, and outbox are
 * all handled by the registrar decorator stack this Action calls; a retry with the same key replays.
 */
final class CommissionIdentifier
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly IdempotencyService $idempotency,
    ) {}

    public function __invoke(CommissionData $data): Identifier
    {
        $requestHash = hash('sha256', json_encode(
            ['commission', (string) $data->identifier, $data->alias?->value, $data->subject?->reference()],
            JSON_THROW_ON_ERROR,
        ));

        return $this->idempotency->rememberIdentifier($data->actor, $data->idempotencyKey, $requestHash, function () use ($data): Identifier {
            $this->registrar->apply(new Commission(
                identifier: $data->identifier,
                actor: $data->actor,
                occurredAt: $data->occurredAt,
                correlationId: $data->correlationId,
                idempotencyKey: $data->idempotencyKey,
                alias: $data->alias,
                description: $data->description,
                subject: $data->subject,
            ));

            return $data->identifier;
        });
    }
}
