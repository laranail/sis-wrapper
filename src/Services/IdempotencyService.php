<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Enums\IdempotencyStatus;
use Simtabi\Laranail\SIS\Exception\IdempotencyConflictException;
use Simtabi\Laranail\SIS\Models\SisIdempotencyKey;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Identifier;
use Throwable;

/**
 * Idempotency for writes, keyed on the REQUEST payload — not the minted command. A write that a client
 * retries on a timeout must return the SAME identifier without acting twice (burning a second serial,
 * repeating a transition). The key is CLAIMED before the operation runs: a row is inserted first and the
 * unique (actor, key) index is the concurrency guard, so two in-flight requests with the same key cannot
 * both act. The key is scoped to (actor, key), never key alone — a global namespace is a cross-tenant
 * replay. A key reused with a different payload is a conflict, not a guess.
 */
final class IdempotencyService
{
    /**
     * Run $operation at most once for a given (actor, key); on a matching replay, return the stored
     * identifier without re-running it. An empty key opts out (the caller took responsibility).
     *
     * @param  callable(): Identifier  $operation
     */
    public function rememberIdentifier(Actor $actor, string $key, string $requestHash, callable $operation): Identifier
    {
        if ($key === '') {
            return $operation();
        }

        $reference = $actor->reference();

        // Claim the key BEFORE the operation. The unique (actor_reference, idempotency_key) index makes a
        // concurrent or prior claim fail the insert — that is how a replay is detected, without a
        // check-then-act race that could let two requests both burn a serial.
        try {
            $claim = SisIdempotencyKey::query()->create([
                'actor_reference' => $reference,
                'idempotency_key' => $key,
                'request_hash' => $requestHash,
                'response' => null,
                'status' => IdempotencyStatus::Pending,
                'created_at' => Date::now(),
                'expires_at' => Date::now()->addHours(Config::integer('sis.idempotency.window_hours', 72)),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->replay($reference, $key, $requestHash);
        }

        // We own the claim. Run the operation; if it fails, release the claim so a fresh retry can proceed.
        try {
            $identifier = $operation();
        } catch (Throwable $e) {
            $claim->delete();

            throw $e;
        }

        $claim->update([
            'response' => (string) $identifier,
            'status' => IdempotencyStatus::Applied,
        ]);

        return $identifier;
    }

    /**
     * A prior claim for this (actor, key) exists. Replay its stored identifier when the payload matches and
     * the operation finished; a mismatched payload is a conflict and a still-pending claim (a concurrent
     * request that has not committed, or a crashed one) must not act again — both throw rather than guess.
     */
    private function replay(string $reference, string $key, string $requestHash): Identifier
    {
        $existing = SisIdempotencyKey::query()
            ->where('actor_reference', $reference)
            ->where('idempotency_key', $key)
            ->first();

        if (!$existing instanceof SisIdempotencyKey || $existing->request_hash !== $requestHash) {
            throw IdempotencyConflictException::of($key);
        }

        if ($existing->status !== IdempotencyStatus::Applied || $existing->response === null) {
            throw IdempotencyConflictException::inProgress($key);
        }

        return app(SisEngine::class)->parse($existing->response);
    }
}
