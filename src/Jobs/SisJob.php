<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * The base for every SIS job: explicit retry policy and the configured connection/queue (never sync by
 * default, never assuming Redis). Concrete jobs are idempotent and call a service or Action — none
 * reimplements one.
 */
abstract class SisJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 300;

    public function __construct()
    {
        $connection = config('sis.queue.connection');
        if (is_string($connection)) {
            $this->onConnection($connection);
        }

        $queue = config('sis.queue.queue');
        if (is_string($queue)) {
            $this->onQueue($queue);
        }
    }
}
