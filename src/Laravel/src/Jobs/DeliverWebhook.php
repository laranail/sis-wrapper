<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Simtabi\Laranail\SIS\Contract\WebhookDispatcher;
use Simtabi\Laranail\SIS\Exception\BlockedUrlException;
use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;
use Simtabi\Laranail\SIS\Webhooks\CircuitBreaker;

/**
 * Delivers one webhook: signed, retried with backoff, and skipped while the endpoint's circuit is open. A
 * blocked URL is permanent (do not retry); a transient failure is released back to the queue.
 */
final class DeliverWebhook extends SisJob
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        private readonly int $endpointId,
        private readonly array $payload,
    ) {
        parent::__construct();
    }

    public function handle(WebhookDispatcher $dispatcher, CircuitBreaker $breaker): void
    {
        $endpoint = SisWebhookEndpoint::query()->find($this->endpointId);

        if (!$endpoint instanceof SisWebhookEndpoint || !$endpoint->active || $breaker->isOpen($endpoint)) {
            return;
        }

        try {
            $delivered = $dispatcher->dispatch($endpoint, $this->payload);
        } catch (BlockedUrlException) {
            // A blocked URL is a permanent failure — record it and stop, never retry into the same wall.
            $breaker->recordFailure($endpoint);

            return;
        }

        if ($delivered) {
            $breaker->recordSuccess($endpoint);

            return;
        }

        $breaker->recordFailure($endpoint);
        $this->release(30);
    }
}
