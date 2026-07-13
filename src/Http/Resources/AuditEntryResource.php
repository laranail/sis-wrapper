<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Simtabi\Laranail\SIS\Models\SisAudit;

/**
 * The wire format for one append-only audit row (§2.9). The hash chain is exposed so a consumer can verify
 * tamper-evidence out of band; the actor is shown as its morph reference, never a raw class name.
 *
 * @mixin SisAudit
 */
final class AuditEntryResource extends JsonResource
{
    public static $wrap = null;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'identifier' => $this->identifier,
            'action' => $this->action,
            'actor' => $this->actor_type !== null
                ? ['type' => $this->actor_type, 'id' => $this->actor_id]
                : null,
            'before_state' => $this->before_state,
            'after_state' => $this->after_state,
            'ability' => $this->ability,
            'verdict' => $this->verdict?->value,
            'correlation_id' => $this->correlation_id,
            'hash' => $this->hash,
            'prev_hash' => $this->prev_hash,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
