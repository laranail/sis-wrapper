<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Profile\ClassDefinition;

/**
 * The input to reserving an identifier — a dumb value object carrying an already-resolved `ClassDefinition`.
 * The reservation reason is the one intrinsic invariant it guards; whether the scope matches the class and
 * the serial fits its width are validated by the engine when the Action mints through the codec, so a Laravel
 * rule never restates a rule the core owns.
 */
final readonly class ReserveData
{
    public function __construct(
        public ClassDefinition $class,
        public ?string $scope,
        public string $reason,
        public Actor $actor,
        public DateTimeImmutable $occurredAt,
        public string $correlationId,
        public string $idempotencyKey,
        public int $width = 6,
        public ?int $serial = null,
        public ?string $reservedBy = null,
        public ?DateTimeImmutable $expiresAt = null,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reservation must record why it was made (SIM-STD-0001:2026 §6.5).');
        }
    }
}
