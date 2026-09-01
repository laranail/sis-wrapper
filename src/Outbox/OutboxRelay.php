<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Outbox;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Date;
use Simtabi\Laranail\SIS\Models\SisOutbox;

/**
 * Drains the outbox after commit: dispatches each unrelayed message through Laravel's event dispatcher
 * (keyed by the domain event class, with the row as payload) and marks it relayed. At-least-once — a crash
 * between dispatch and mark re-delivers, so listeners must be idempotent. The scheduled RelayOutbox job is
 * the durable driver; a decorator may call this eagerly after a write for lower latency.
 */
final class OutboxRelay
{
    public function __construct(
        private readonly Dispatcher $events,
    ) {}

    public function relayPending(int $limit = 100): int
    {
        $relayed = 0;

        SisOutbox::query()
            ->whereNull('relayed_at')
            ->orderBy('id')
            ->limit($limit)
            ->get()
            ->each(function (SisOutbox $message) use (&$relayed): void {
                $this->events->dispatch($message->event_type, [$message]);
                $message->setAttribute('relayed_at', Date::now());
                $message->save();
                $relayed++;
            });

        return $relayed;
    }
}
