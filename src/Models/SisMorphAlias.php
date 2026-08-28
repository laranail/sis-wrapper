<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Simtabi\Laranail\SIS\Models\Concerns\UsesSisConnection;
use Simtabi\Laranail\SIS\Database\Factories\SisMorphAliasFactory;

/** An append-only record of a morph alias allocation (decision D4). Config resolves; this table remembers. */
final class SisMorphAlias extends Model
{
    /** @use HasFactory<SisMorphAliasFactory> */
    use HasFactory;

    use UsesSisConnection;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $primaryKey = 'alias';

    protected $keyType = 'string';

    protected $fillable = ['alias', 'model_class', 'created_at'];

    public function getTable(): string
    {
        return $this->sisTableName('morph_aliases');
    }

    protected static function newFactory(): SisMorphAliasFactory
    {
        return SisMorphAliasFactory::new();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }
}
