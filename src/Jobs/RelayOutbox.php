<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Simtabi\Laranail\SIS\Outbox\OutboxRelay;
use Illuminate\Contracts\Queue\ShouldBeUnique;

/** Drains the transactional outbox (§2.7). Unique so overlapping runs do not double-dispatch. */
final class RelayOutbox extends SisJob implements ShouldBeUnique
{
    public function uniqueId(): string
    {
        return 'sis:relay-outbox';
    }

    public function handle(OutboxRelay $relay): void
    {
        $relay->relayPending(500);
    }
}
