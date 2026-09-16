<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Simtabi\Laranail\SIS\Models\SisAudit;
use Simtabi\Laranail\SIS\Http\Resources\AuditEntryResource;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Simtabi\Laranail\SIS\Http\Controllers\Concerns\ResolvesIdentifier;

/** GET identifiers/{identifier}/audit — the append-only trail for one identifier (§2.9), oldest first. */
final class AuditController
{
    use ResolvesIdentifier;

    public function __invoke(string $identifier): AnonymousResourceCollection
    {
        $parsed = $this->identifier($identifier);

        $rows = SisAudit::query()
            ->where('identifier', (string) $parsed)
            ->orderBy('id')
            ->get();

        return AuditEntryResource::collection($rows);
    }
}
