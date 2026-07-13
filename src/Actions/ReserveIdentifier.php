<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Authorization\AuthorizationContext;
use Simtabi\Laranail\SIS\Authorization\Authorizer;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Contract\SerialIssuer;
use Simtabi\Laranail\SIS\Data\ReserveData;
use Simtabi\Laranail\SIS\Services\IdempotencyService;
use Simtabi\SIS\Command\Reserve;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Reserve an identifier — the create path. An Action is the ONLY thing that builds a core Command, so the
 * HTTP and CLI paths share exactly one implementation. It authorizes BEFORE issuing a serial (so an
 * unauthorised actor never burns one) and wraps the mint in idempotency keyed on the REQUEST payload, so a
 * retry replays the same identifier without burning a second serial.
 */
final class ReserveIdentifier
{
    public function __construct(
        private readonly Registrar $registrar,
        private readonly SerialIssuer $serials,
        private readonly Authorizer $authorizer,
        private readonly IdempotencyService $idempotency,
        private readonly SisEngine $engine,
    ) {}

    public function __invoke(ReserveData $data): Identifier
    {
        $this->authorizer->authorizeAbility(
            $data->actor,
            SisAbility::Reserve,
            new AuthorizationContext($data->class, $data->scope),
            $data->correlationId,
            $data->idempotencyKey,
        );

        $requestHash = hash('sha256', json_encode(
            ['reserve', $data->class->code, $data->scope, $data->reason, $data->width],
            JSON_THROW_ON_ERROR,
        ));

        return $this->idempotency->rememberIdentifier($data->actor, $data->idempotencyKey, $requestHash, function () use ($data): Identifier {
            $serial = $data->serial ?? $this->serials->next($data->class, $data->scope);

            $command = new Reserve(
                identifier: $this->engine->codec()->mint($data->class, $serial, $data->scope, $data->width),
                reason: $data->reason,
                actor: $data->actor,
                occurredAt: $data->occurredAt,
                correlationId: $data->correlationId,
                idempotencyKey: $data->idempotencyKey,
                reservedBy: $data->reservedBy,
                expiresAt: $data->expiresAt,
            );

            $this->registrar->apply($command);

            return $command->identifier;
        });
    }
}
