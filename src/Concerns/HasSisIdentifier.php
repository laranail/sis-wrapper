<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Gives a consumer model its SIS identifier — relations and accessors ONLY.
 *
 * IT NEVER MINTS. There is deliberately no `booted()` hook that mints on `created`: minting is an Action,
 * so it is authorized, audited, and outboxed. A model observer that mints behind your back is a back door
 * around every guarantee in this design, and it is the single most tempting shortcut in the package. Do
 * not add one.
 *
 * @mixin Model
 */
trait HasSisIdentifier
{
    /** @return MorphOne<SisRecord, $this> */
    public function sisIdentifier(): MorphOne
    {
        return $this->morphOne(SisRecord::class, 'subject');
    }

    public function sisId(): ?Identifier
    {
        return $this->sisIdentifier()->first()?->identifier();
    }

    public function sisAlias(): ?string
    {
        $alias = $this->sisIdentifier()->first()?->getAttribute('alias');

        return $alias === null ? null : (string) $alias;
    }

    public function scopeWhereSisAlias(Builder $query, string $alias): Builder
    {
        return $query->whereHas('sisIdentifier', static fn (Builder $q): Builder => $q->where('alias', strtoupper($alias)));
    }

    /** Models that should carry a SIS identifier but do not — a useful integrity scope. */
    public function scopeWithoutSisIdentifier(Builder $query): Builder
    {
        return $query->whereDoesntHave('sisIdentifier');
    }
}
