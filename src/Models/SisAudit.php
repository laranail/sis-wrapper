<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Simtabi\Laranail\SIS\Database\Factories\SisAuditFactory;
use Simtabi\Laranail\SIS\Enums\AuditVerdict;
use Simtabi\Laranail\SIS\Models\Concerns\UsesSisConnection;

/**
 * A row in the append-only audit trail (§2.9). Read-and-insert only; the storage-layer trigger rejects any
 * UPDATE or DELETE, so this model never carries an updated_at.
 *
 * @property int $id
 * @property string $identifier
 * @property string $action
 * @property ?string $actor_type
 * @property ?string $actor_id
 * @property ?string $before_state
 * @property ?string $after_state
 * @property ?string $ability
 * @property ?AuditVerdict $verdict
 * @property string $correlation_id
 * @property ?string $idempotency_key
 * @property ?array<string, mixed> $context
 * @property ?string $hash
 * @property ?string $prev_hash
 * @property ?CarbonImmutable $created_at
 */
final class SisAudit extends Model
{
    /** @use HasFactory<SisAuditFactory> */
    use HasFactory;

    use UsesSisConnection;

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'context' => 'array',
            'verdict' => AuditVerdict::class,
            'created_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return $this->sisTableName('audit');
    }

    protected static function newFactory(): SisAuditFactory
    {
        return SisAuditFactory::new();
    }

    /**
     * The actor who performed the audited effect — a polymorphic reference under the morph map.
     *
     * @return MorphTo<Model, $this>
     */
    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }
}
