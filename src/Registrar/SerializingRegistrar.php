<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Illuminate\Support\Facades\Cache;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Decision\Decision;

/**
 * Serialises every register write behind a single named lock so the append-only audit hash chain cannot
 * FORK. Without it, two concurrent writes each read the current chain head (AuditWriter reads the latest
 * `hash` with no lock) and chain their new rows off the SAME prev_hash — two rows share a predecessor and the
 * tamper-evident chain splits, defeating its whole purpose.
 *
 * The lock is held across the ENTIRE inner write — its transaction AND its commit — not just the hash read:
 * this decorator sits OUTSIDE Authorizing and Transactional in the stack (Logging -> OutboxRelaying ->
 * ConstraintTranslating -> Serializing -> Authorizing -> Transactional -> Eloquent). So the next writer only
 * acquires the lock after the prior one has committed, and therefore reads the committed head. Wrapping
 * Authorizing too means a registrar-path deny-audit row (written by the Authorizer, outside the transaction)
 * is serialised against the effect-audit rows as well — every append to the chain goes through one door.
 *
 * Two residuals are left honest rather than papered over:
 *  (i) The pre-flight Reserve deny-audit runs in ReserveIdentifier BEFORE the registrar, so it is NOT inside
 *      this lock: a rare unauthorized-reserve racing another append could still fork on that one row. It is
 *      left unserialised deliberately — this lock is non-reentrant, so acquiring it again in the Action would
 *      deadlock against the acquisition here. The deny row is a low-value leaf (no successor chains off a
 *      denial in practice), so the trade is a conscious one, not an oversight.
 *  (ii) Correct serialisation ACROSS servers needs an atomic cache lock store (redis, database, memcached),
 *      the same constraint the schedule guard enforces for onOneServer(). `array` (tests / single process)
 *      and a single-server deployment are fine; `file` cannot provide a cross-process atomic lock and must
 *      not be relied on here.
 */
final class SerializingRegistrar implements Registrar
{
    public function __construct(
        private readonly Registrar $inner,
    ) {}

    public function apply(Command $command): Decision
    {
        /** @var Decision $decision block() returns the closure's value as mixed; the closure returns a Decision */
        $decision = Cache::lock('sis:register-write', 15)->block(15, fn (): Decision => $this->inner->apply($command));

        return $decision;
    }
}
