<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Illuminate\Support\Facades\DB;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Decision\Decision;

/**
 * Wraps the write in one transaction: the effects, the audit rows, the outbox rows, and the idempotency
 * record all commit together or not at all. On any exception the transaction rolls back, so a failed
 * command leaves nothing behind.
 */
final class TransactionalRegistrar implements Registrar
{
    public function __construct(
        private readonly Registrar $inner,
    ) {}

    public function apply(Command $command): Decision
    {
        /** @var string|null $name */
        $name = config('sis.database.connection');

        return DB::connection($name)->transaction(fn (): Decision => $this->inner->apply($command));
    }
}
