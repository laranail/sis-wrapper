<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\Identifier;

/** Assign the mnemonic alias. Frozen once written (§5). */
final readonly class AssignAlias implements Effect
{
    public function __construct(
        public Identifier $identifier,
        public Alias $alias,
        public DateTimeImmutable $at,
    ) {}
}
