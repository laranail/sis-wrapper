<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use DateTimeInterface;
use Simtabi\Laranail\SIS\Enums\AuditVerdict;
use Simtabi\Laranail\SIS\Enums\SisAbility;
use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\SIS\Identifier\Actor;

/**
 * The one place an audit row is written (§2.9). Both an applied effect (from the EffectApplier, verdict
 * Allowed) and a denied authorization attempt (from the Authorizer, verdict Denied) record through here, so
 * the hash chain stays a single unbroken sequence across effect and deny rows alike.
 *
 * The hash chain is preserved EXACTLY as it was on the effect path: each row's hash is
 * `sha256(prev_hash . json([identifier, action, actor.reference, before, after, correlation_id, context]))`,
 * gated on `sis.audit.hash_chain`. The ability and verdict are recorded on the row but are deliberately NOT
 * folded into the hashed payload — the chain shape is a stored contract and must not shift.
 *
 * The table is append-only (INSERT only, enforced by trigger); this only ever creates.
 */
final class AuditWriter
{
    /** @param array<string, mixed> $context redacted */
    public function write(
        ?string $identifier,
        string $action,
        Actor $actor,
        ?string $before,
        ?string $after,
        ?SisAbility $ability,
        ?AuditVerdict $verdict,
        string $correlationId,
        string $idempotencyKey,
        array $context,
        DateTimeInterface $at,
    ): void {
        $prevHash = null;
        $hash = null;

        if ((bool) config('sis.audit.hash_chain', true)) {
            // Read the committed head. Concurrent writers must be serialised OUTSIDE this call (the
            // SerializingRegistrar holds a lock across the whole transaction+commit); an unlocked read here
            // would let two writers chain off the same head and FORK the chain. See SerializingRegistrar.
            /** @var string|null $prevHash */
            $prevHash = SisAudit::query()->orderByDesc('id')->value('hash');
            $hash = self::chainHash(
                $prevHash, $identifier, $action, $actor->reference(),
                $before, $after, $correlationId, $context,
            );
        }

        SisAudit::query()->create([
            'identifier' => $identifier,
            'action' => $action,
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'before_state' => $before,
            'after_state' => $after,
            'ability' => $ability?->value,
            'verdict' => $verdict,
            'correlation_id' => $correlationId,
            'idempotency_key' => $idempotencyKey,
            'context' => $context,
            'hash' => $hash,
            'prev_hash' => $prevHash,
            'created_at' => $at,
        ]);
    }

    /**
     * The one definition of a chain link. write() computes each row's hash through here, and the verifier
     * (IntegrityService::verifyAuditChain) recomputes it through the SAME method — so the writer and the
     * checker cannot drift: a change to the hashed shape changes both at once, and a stored hash that no
     * longer recomputes is tampering, not a version skew. The ability and verdict are recorded on the row
     * but are deliberately NOT folded in — the chain shape is a stored contract and must not shift.
     *
     * @param  string  $actorReference  the actor's PII-free reference, `actor_type . ':' . actor_id`
     * @param  array<string, mixed>  $context  the redacted context, re-encoded exactly as stored
     */
    public static function chainHash(
        ?string $prevHash,
        ?string $identifier,
        string $action,
        string $actorReference,
        ?string $before,
        ?string $after,
        string $correlationId,
        array $context,
    ): string {
        $content = json_encode([
            $identifier, $action, $actorReference,
            $before, $after, $correlationId, $context,
        ], JSON_THROW_ON_ERROR);

        return hash('sha256', (string) $prevHash . $content);
    }
}
