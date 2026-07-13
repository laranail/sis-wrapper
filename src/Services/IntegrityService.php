<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Contract\SisEngine;
use Simtabi\SIS\Identifier\SpecEdition;

/**
 * Recomputes and checks the check characters of stored identifiers (§4). A stored identifier that no longer
 * verifies is corruption — or a bug in us — and a human reads about it today. Grandfathered pre-SIS rows
 * (Annex C.3) are skipped: they were never expected to satisfy the SIS/1 grammar.
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
}
