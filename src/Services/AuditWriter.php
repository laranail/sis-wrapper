<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use DateTimeInterface;
use Simtabi\Laranail\SIS\Authorization\SisAbility;
use Simtabi\Laranail\SIS\Enums\AuditVerdict;
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
            /** @var string|null $prevHash */
            $prevHash = SisAudit::query()->orderByDesc('id')->value('hash');
            $content = json_encode([
                $identifier, $action, $actor->reference(),
                $before, $after, $correlationId, $context,
            ], JSON_THROW_ON_ERROR);
            $hash = hash('sha256', (string) $prevHash . $content);
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
}
