<?php

declare(strict_types=1);

namespace Simtabi\SIS\Decision;

use DateTimeImmutable;
use Simtabi\SIS\Contract\Effect;
use Simtabi\SIS\Identifier\Identifier;

/**
 * Delete a RESERVED row — the only deletion the register ever permits (a released reservation). A
 * commissioned row is never deleted; the storage-layer trigger enforces it.
 */
final readonly class DeleteRecord implements Effect
{
    public function __construct(
        public Identifier $identifier,
        public DateTimeImmutable $at,
    ) {}
}
