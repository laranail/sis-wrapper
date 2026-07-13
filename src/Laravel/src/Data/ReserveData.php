<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Data;

use DateTimeImmutable;
use InvalidArgumentException;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Policy\ScopePolicy;
use Simtabi\SIS\Policy\SerialPolicy;

/**
 * The input to reserving an identifier. THE CONSTRUCTOR IS THE REAL VALIDATION GATE — it protects the HTTP
 * path, the CLI path, the queue, and a seeder alike, not just one of them. It validates through the core
 * policies, so a Laravel rule never restates a rule the core owns.
 */
final readonly class ReserveData
{
    public function __construct(
        public IdClass $class,
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

        ScopePolicy::assertMatches($class, $scope);
        SerialPolicy::assertWidth($width);
    }
}
