<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Contract;

use Simtabi\Laranail\SIS\Models\SisWebhookEndpoint;

/**
 * Delivers a signed payload to a webhook endpoint. A consumer may bind their own transport behind this
 * seam. Every implementation runs the endpoint URL through the SSRF guard and does not follow redirects.
 */
interface WebhookDispatcher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return bool whether the endpoint accepted the delivery
     */
    public function dispatch(SisWebhookEndpoint $endpoint, array $payload): bool;
}
