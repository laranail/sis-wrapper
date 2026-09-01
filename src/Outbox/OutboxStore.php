<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Outbox;

use Simtabi\Laranail\SIS\Models\SisOutbox;
use Simtabi\SIS\Contract\DomainEvent;
use Simtabi\SIS\Event\AbstractEvent;

/**
 * Writes the core's returned events to the transactional outbox — in the SAME transaction as the effects.
 * Without this, a dispatch inside the transaction fires on a write that rolls back, and a dispatch after
 * commit is lost if the process dies. The relay drains the outbox after commit; relay is at-least-once, so
 * every listener must be idempotent.
 */
final class OutboxStore
{
    /** @param list<DomainEvent> $events */
    public function write(array $events): void
    {
        foreach ($events as $event) {
            SisOutbox::query()->create([
                'event_type' => $event::class,
                'identifier' => $event->identifier(),
                'payload' => $this->payload($event),
                'correlation_id' => $event instanceof AbstractEvent ? $event->correlationId : '',
                'available_at' => $event->occurredAt(),
                'created_at' => $event->occurredAt(),
                'attempts' => 0,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function payload(DomainEvent $event): array
    {
        $decoded = json_decode(json_encode($event, JSON_THROW_ON_ERROR), true);

        return is_array($decoded) ? $decoded : [];
    }
}
