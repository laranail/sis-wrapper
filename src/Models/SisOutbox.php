<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Simtabi\Laranail\SIS\Models\Concerns\UsesSisConnection;
use Simtabi\Laranail\SIS\Database\Factories\SisOutboxFactory;

/**
 * A pending or relayed outbox event (§2.7). Written with the effects, relayed after commit.
 *
 * @property string $event_type
 * @property ?string $identifier
 * @property array<string, mixed> $payload
 * @property ?CarbonImmutable $relayed_at
 */
final class SisOutbox extends Model
{
    /** @use HasFactory<SisOutboxFactory> */
    use HasFactory;

    use UsesSisConnection;

    public $timestamps = false;

    protected $guarded = ['id'];

    public function getTable(): string
    {
        return $this->sisTableName('outbox');
    }

    protected static function newFactory(): SisOutboxFactory
    {
        return SisOutboxFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload'      => 'array',
            'available_at' => 'immutable_datetime',
            'relayed_at'   => 'immutable_datetime',
            'created_at'   => 'immutable_datetime',
        ];
    }
}
