<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Simtabi\Laranail\SIS\Database\Factories\SisWebhookEndpointFactory;
use Simtabi\Laranail\SIS\Enums\CircuitState;
use Simtabi\Laranail\SIS\Models\Concerns\UsesSisConnection;

/**
 * A webhook endpoint (§2.13). The secret is encrypted at rest and hidden from serialisation — it is
 * write-only over the API (accepted on create, never returned; regenerate rather than read).
 *
 * @property int $id
 * @property string $url
 * @property string $secret
 * @property array<int, string> $events
 * @property bool $active
 * @property CircuitState $circuit_state
 * @property ?CarbonImmutable $circuit_opened_at
 * @property int $failures
 */
final class SisWebhookEndpoint extends Model
{
    /** @use HasFactory<SisWebhookEndpointFactory> */
    use HasFactory;

    use UsesSisConnection;

    protected $guarded = ['id'];

    /**
     * Mirror the DB defaults in memory so a freshly-created row carries a closed circuit and zero failures
     * before it is reloaded — the circuit breaker forceFills these columns and they are NOT NULL.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
        'circuit_state' => 'closed',
        'failures' => 0,
    ];

    /** @var list<string> */
    protected $hidden = ['secret'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'secret' => 'encrypted',
            'events' => 'array',
            'active' => 'boolean',
            'circuit_state' => CircuitState::class,
            'circuit_opened_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return $this->sisTableName('webhook_endpoints');
    }

    protected static function newFactory(): SisWebhookEndpointFactory
    {
        return SisWebhookEndpointFactory::new();
    }

    /**
     * The optional owner of this endpoint — a polymorphic reference under the morph map.
     *
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo('owner');
    }
}
