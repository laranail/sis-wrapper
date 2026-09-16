<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Actions;

use Simtabi\SIS\Identifier\Identifier;
use Simtabi\Laranail\SIS\Read\SisReadModel;

/** Resolve a mnemonic alias (ADIQ, MALISA) to its canonical identifier (§5). A read; no Registrar. */
final class ResolveAlias
{
    public function __construct(
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $alias): ?Identifier
    {
        return $this->read->byAlias($alias)?->identifier();
    }
}
