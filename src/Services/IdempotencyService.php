<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Enums\IdempotencyStatus;
use Simtabi\Laranail\SIS\Exception\IdempotencyConflictException;
use Simtabi\Laranail\SIS\Models\SisIdempotencyKey;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Idempotency for writes, keyed on the REQUEST payload — not the minted command. A POST /identifiers that a
 * client retries on a timeout must return the SAME identifier without issuing a second serial, so the key
 * is stored BEFORE the serial is burned and the resulting identifier is replayed. The key is scoped to
 * (actor, key), never key alone — a global namespace is a cross-tenant replay. A key reused with a
 * different payload is a conflict, not a guess.
 */
final class IdempotencyService
{
    /**
     * Run $operation once for a given (actor, key); on a matching replay, return the stored identifier
     * without re-running it. An empty key opts out (the caller took responsibility).
     *
     * @param  callable(): Identifier  $operation
     */
    public function rememberIdentifier(Actor $actor, string $key, string $requestHash, callable $operation): Identifier
    {
        if ($key === '') {
            return $operation();
        }

        $reference = $actor->reference();

        $existing = SisIdempotencyKey::query()
            ->where('actor_reference', $reference)
            ->where('idempotency_key', $key)
            ->first();

        if ($existing instanceof SisIdempotencyKey) {
            if ($existing->request_hash !== $requestHash) {
                throw IdempotencyConflictException::of($key);
            }

            return app(SisEngine::class)->parse($existing->response ?? '');
        }

        $identifier = $operation();

        SisIdempotencyKey::query()->create([
            'actor_reference' => $reference,
            'idempotency_key' => $key,
            'request_hash' => $requestHash,
            'response' => (string) $identifier,
            'status' => IdempotencyStatus::Applied,
            'created_at' => Date::now(),
            'expires_at' => Date::now()->addHours(Config::integer('sis.idempotency.window_hours', 72)),
        ]);

        return $identifier;
    }
}
