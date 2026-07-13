<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SubjectRef;

/** Attach the polymorphic subject — the thing the identifier names. Frozen once commissioned (§9). */
final readonly class SetSubject implements Effect
{
    public function __construct(
        public Identifier $identifier,
        public SubjectRef $subject,
        public DateTimeImmutable $at,
    ) {}
}
