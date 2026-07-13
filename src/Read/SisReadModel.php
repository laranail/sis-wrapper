<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Read;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * The query side (§2.11). Reads hit the register directly and NEVER go through the Registrar — CQRS-lite,
 * kept deliberately simple. This is where the API's list and lookup endpoints resolve.
 */
final class SisReadModel
{
    public function find(Identifier $identifier): ?SisRecord
    {
        return SisRecord::query()->find((string) $identifier);
    }

    public function byAlias(string $alias): ?SisRecord
    {
        return SisRecord::query()->where('alias', strtoupper($alias))->first();
    }

    public function bySubject(SubjectRef $subject): ?SisRecord
    {
        return SisRecord::query()
            ->where('subject_type', $subject->type)
            ->where('subject_id', $subject->id)
            ->first();
    }

    /** @return LengthAwarePaginator<int, SisRecord> */
    public function paginate(int $perPage = 25): LengthAwarePaginator
    {
        return SisRecord::query()->orderBy('created_at')->paginate($perPage);
    }

    /**
     * The supersession chain forward from an identifier to its terminal successor (§8), cycle-safe.
     *
     * @return list<Identifier>
     */
    public function chain(Identifier $identifier): array
    {
        $chain = [];
        $seen = [$identifier->comparable() => true];
        $cursor = $this->find($identifier)?->superseded_by;

        while ($cursor !== null) {
            $next = app(SisEngine::class)->parse($cursor);

            if (isset($seen[$next->comparable()])) {
                break;
            }

            $chain[] = $next;
            $seen[$next->comparable()] = true;
            $cursor = $this->find($next)?->superseded_by;
        }

        return $chain;
    }

    /** The current successor of an identifier — the last link in its chain, or itself if never superseded. */
    public function terminalSuccessor(Identifier $identifier): Identifier
    {
        $chain = $this->chain($identifier);

        return $chain === [] ? $identifier : $chain[array_key_last($chain)];
    }
}
