<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Config;
use Simtabi\Laranail\SIS\Database\Factories\SisRecordFactory;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Enums\LifecycleState;
use Simtabi\SIS\Enums\SimClass;
use Simtabi\SIS\Identifier\Identifier;

/**
 * A row in the register (§9). It has NO mass-assignable attributes: every write goes through the
 * Registrar, so a fillable array would be a door that should not exist. The identifier is the primary key
 * (non-incrementing string) and the route key.
 *
 * @property string $identifier
 * @property string $class
 * @property ?string $scope
 * @property int $serial
 * @property string $spec_edition
 * @property ?string $alias
 * @property LifecycleState $state
 * @property string $description
 * @property ?string $owner
 * @property ?string $subtype
 * @property ?string $subject_type
 * @property ?string $subject_id
 * @property ?string $reserved_by
 * @property ?string $reserved_reason
 * @property ?CarbonImmutable $reserved_at
 * @property ?CarbonImmutable $expires_at
 * @property ?CarbonImmutable $commissioned_at
 * @property ?CarbonImmutable $decommissioned_at
 * @property ?string $superseded_by
 */
final class SisRecord extends Model
{
    /** @use HasFactory<SisRecordFactory> */
    use HasFactory;

    protected $primaryKey = 'identifier';

    public $incrementing = false;

    protected $keyType = 'string';

    /** No mass assignment — writes go through the Registrar, never a fillable array. */
    protected $fillable = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // The class is a bare code string, not the SIM enum — the register is profile-driven and may
            // hold a consuming company's own class codes, which are not SimClass cases.
            'state' => LifecycleState::class,
            'serial' => 'integer',
            'reserved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'commissioned_at' => 'immutable_datetime',
            'decommissioned_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return Config::string('sis.database.prefix', 'sis_') . 'register';
    }

    public function getConnectionName(): ?string
    {
        $connection = config('sis.database.connection');

        return is_string($connection) ? $connection : null;
    }

    public function getRouteKeyName(): string
    {
        return 'identifier';
    }

    protected static function newFactory(): SisRecordFactory
    {
        return SisRecordFactory::new();
    }

    /** The identifier as a validated core value object. */
    public function identifier(): Identifier
    {
        return app(SisEngine::class)->parse($this->identifier);
    }

    /**
     * The thing this identifier names (§2.5). Polymorphic under the enforced morph map.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo('subject');
    }

    /**
     * The owning person identifier (§9), a self reference.
     *
     * @return BelongsTo<self, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(self::class, 'owner', 'identifier');
    }

    /**
     * The successor in the supersession chain (§8), a self reference.
     *
     * @return BelongsTo<self, $this>
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by', 'identifier');
    }
}
