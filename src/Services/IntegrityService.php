<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\SpecEdition;

/**
 * Recomputes and checks the check characters of stored identifiers (§4), and verifies the append-only audit
 * hash chain end to end (§2.9). A stored identifier that no longer verifies is corruption — or a bug in us —
 * and a human reads about it today. Grandfathered pre-SIS rows (Annex C.3) are skipped: they were never
 * expected to satisfy the SIS/1 grammar. The chain check is the counterpart the check-character scan alone
 * could never provide: it proves nobody rewrote a committed audit row under the append-only trigger.
 */
final class IntegrityService
{
    /** Whether a stored identifier's grammar and check characters verify. */
    public function isIntact(string $identifier): bool
    {
        return app(SisEngine::class)->validate($identifier);
    }

    /**
     * A sample of corrupt (non-verifying) identifiers, for sis:doctor.
     *
     * @return list<string>
     */
    public function sampleCorrupt(int $limit = 100): array
    {
        $corrupt = [];

        SisRecord::query()
            ->where('spec_edition', '!=', SpecEdition::PRE_SIS)
            ->limit($limit)
            ->get()
            ->each(function (SisRecord $record) use (&$corrupt): void {
                if (!$this->isIntact($record->identifier)) {
                    $corrupt[] = $record->identifier;
                }
            });

        return $corrupt;
    }

    /**
     * Verifies the audit hash chain end to end (§2.9), walking every row by ascending id and checking two
     * invariants per row:
     *  - LINKAGE: prev_hash equals the previous row's stored hash (the first row's prev_hash is null).
     *  - INTEGRITY: recomputing the hash from the row's own fields — through the SAME AuditWriter::chainHash
     *    the writer used, so checker and writer cannot drift — reproduces the stored hash.
     * A break in either is tampering under the append-only trigger, or a fork from an unserialised concurrent
     * write — the alarm the chain exists to raise. The actor reference is rebuilt as `actor_type:actor_id`
     * (exactly Actor::reference()), and context is re-encoded from the model's array cast, matching how the
     * row was originally hashed.
     *
     * Skips cleanly when sis.audit.hash_chain is off: no chain is maintained, so there is nothing to verify.
     *
     * @return list<string> a descriptor per broken row, e.g. "#42" or "#42 (SIM-PRS-100001-9O)"; [] if intact
     */
    public function verifyAuditChain(): array
    {
        if (!(bool) config('sis.audit.hash_chain', true)) {
            return [];
        }

        $broken = [];
        $expectedPrev = null;

        SisAudit::query()->orderBy('id')->each(function (SisAudit $row) use (&$broken, &$expectedPrev): void {
            $recomputed = AuditWriter::chainHash(
                $row->prev_hash,
                $row->identifier,
                $row->action,
                (string) $row->actor_type . ':' . (string) $row->actor_id,
                $row->before_state,
                $row->after_state,
                $row->correlation_id,
                $row->context ?? [],
            );

            if ($row->prev_hash !== $expectedPrev || $row->hash !== $recomputed) {
                $broken[] = '#' . $row->id . ($row->identifier !== null ? " ({$row->identifier})" : '');
            }

            $expectedPrev = $row->hash;
        });

        return $broken;
    }
}
