<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Identifier\Identifier;

/** Walk the supersession chain (§8) to find the current successor, cycle-safe. A read; no Registrar. */
final class TraceSupersessionChain
{
    public function __construct(
        private readonly SisReadModel $read,
    ) {}

    /** @return list<Identifier> the chain forward, terminal successor last */
    public function __invoke(Identifier $identifier): array
    {
        return $this->read->chain($identifier);
    }

    public function terminal(Identifier $identifier): Identifier
    {
        return $this->read->terminalSuccessor($identifier);
    }
}
