<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Identifier;

/** Record that an identifier has been superseded. Never edits the superseded identifier (§8). */
final readonly class SetSupersededBy implements Effect
{
    public function __construct(
        public Identifier $identifier,
        public Identifier $successor,
        public DateTimeImmutable $at,
    ) {}
}
