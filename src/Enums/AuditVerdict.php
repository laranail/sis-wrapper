<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Enums;

/**
 * The authorization outcome recorded on an audit row (§2.9) — whether the resolver `Allowed` or `Denied` the
 * ability the command required. Null on rows that record an effect rather than an authorization decision.
 */
enum AuditVerdict: string
{
    case Allowed = 'allowed';

    case Denied = 'denied';
}
