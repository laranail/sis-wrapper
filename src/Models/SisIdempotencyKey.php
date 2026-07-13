<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\SIS\Database\Factories\SisIdempotencyKeyFactory;
use Simtabi\Laranail\SIS\Enums\IdempotencyStatus;
use Simtabi\Laranail\SIS\Models\Concerns\UsesSisConnection;

/**
 * A stored idempotency key, scoped to (actor_reference, idempotency_key) — never key alone (§2.13).
 *
 * @property string $request_hash
 * @property ?string $response
 * @property IdempotencyStatus $status
 */
final class SisIdempotencyKey extends Model
{
    /** @use HasFactory<SisIdempotencyKeyFactory> */
    use HasFactory;

    use UsesSisConnection;

    public $timestamps = false;

    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => IdempotencyStatus::class,
            'created_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
        ];
    }

    public function getTable(): string
    {
        return $this->sisTableName('idempotency_keys');
    }

    protected static function newFactory(): SisIdempotencyKeyFactory
    {
        return SisIdempotencyKeyFactory::new();
    }
}
