<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Models;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\SIS\Models\Concerns\UsesSisConnection;

/** An append-only record of a morph alias allocation (decision D4). Config resolves; this table remembers. */
final class SisMorphAlias extends Model
{
    use UsesSisConnection;

    public const UPDATED_AT = null;

    protected $primaryKey = 'alias';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['alias', 'model_class', 'created_at'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['created_at' => 'immutable_datetime'];
    }

    public function getTable(): string
    {
        return $this->sisTableName('morph_aliases');
    }
}
