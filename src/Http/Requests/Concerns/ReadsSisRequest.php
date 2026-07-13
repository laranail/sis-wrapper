<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Requests\Concerns;

use Carbon\CarbonImmutable;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Shared readers for the SIS FormRequests: the correlation/idempotency attributes threaded by middleware,
 * the route's identifier segment, and a typed validated-string accessor. Keeps each request's toData() thin.
 */
trait ReadsSisRequest
{
    /** The identifier from the route, already shape-validated by the caller's guard. */
    protected function routeIdentifier(): Identifier
    {
        $value = $this->route('identifier');

        return app(SisEngine::class)->parse(is_string($value) ? $value : '');
    }

    /** The non-domain envelope every write Action takes: who, when, and the threaded correlation/idempotency keys. */
    public function context(Actor $actor): CommandContext
    {
        return new CommandContext(
            actor: $actor,
            occurredAt: CarbonImmutable::now(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
        );
    }

    protected function correlationId(): string
    {
        return $this->requestAttribute('sis.correlation_id');
    }

    protected function idempotencyKey(): string
    {
        return $this->requestAttribute('sis.idempotency_key');
    }

    protected function validatedString(string $key): string
    {
        $value = $this->validated($key);

        return is_string($value) ? $value : '';
    }

    private function requestAttribute(string $key): string
    {
        $value = $this->attributes->get($key);

        return is_string($value) ? $value : '';
    }
}
