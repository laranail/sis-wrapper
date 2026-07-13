<?php

declare(strict_types=1);

namespace Simtabi\SIS\Command;

use DateTimeImmutable;
use LogicException;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Identifier;
use Simtabi\SIS\Identifier\SpecEdition;
use Simtabi\SIS\Identifier\SubjectRef;
use Simtabi\SIS\Policy\ScopePolicy;

/**
 * Builds a command for one identifier. Immutable and fluent — every setter returns a clone, so a
 * half-built minter can never leak state into another call. It returns a Command now instead of hitting a
 * register; the serial is supplied by the caller (the shell issues it atomically) because the core cannot.
 */
final class Minter
{
    public function __construct(
        private readonly IdClass $class,
        private readonly ?string $scope = null,
        private readonly int $width = 6,
        private readonly ?int $serial = null,
        private readonly ?Actor $actor = null,
        private readonly ?DateTimeImmutable $occurredAt = null,
        private readonly ?string $correlationId = null,
        private readonly ?string $idempotencyKey = null,
    ) {}

    // Each setter returns a new instance overriding one field, so a half-built minter never leaks state.
    // (PHP 8.5 `clone with` would express this in one line, but the boundary tool's parser does not yet
    // support it; reintroduce once deptrac's php-parser catches up.)

    /** Form S only: the client this entity belongs to. */
    public function scopedTo(string $clientAlias): self
    {
        return $this->with(scope: strtoupper($clientAlias));
    }

    /** Serial width, 6 to 9 digits. Widening is always safe; narrowing is forbidden. */
    public function withWidth(int $digits): self
    {
        return $this->with(width: $digits);
    }

    /** The issued serial. Required before a command is built — the shell issues it atomically. */
    public function withSerial(int $serial): self
    {
        return $this->with(serial: $serial);
    }

    public function by(Actor $actor): self
    {
        return $this->with(actor: $actor);
    }

    public function at(DateTimeImmutable $occurredAt): self
    {
        return $this->with(occurredAt: $occurredAt);
    }

    public function correlatedBy(string $correlationId): self
    {
        return $this->with(correlationId: $correlationId);
    }

    public function idempotentWith(string $key): self
    {
        return $this->with(idempotencyKey: $key);
    }

    private function with(
        ?string $scope = null,
        ?int $width = null,
        ?int $serial = null,
        ?Actor $actor = null,
        ?DateTimeImmutable $occurredAt = null,
        ?string $correlationId = null,
        ?string $idempotencyKey = null,
    ): self {
        return new self(
            class: $this->class,
            scope: $scope ?? $this->scope,
            width: $width ?? $this->width,
            serial: $serial ?? $this->serial,
            actor: $actor ?? $this->actor,
            occurredAt: $occurredAt ?? $this->occurredAt,
            correlationId: $correlationId ?? $this->correlationId,
            idempotencyKey: $idempotencyKey ?? $this->idempotencyKey,
        );
    }

    /** Build the identifier without producing a command. */
    #[\NoDiscard('building an identifier is pure but wasted if discarded')]
    public function build(): Identifier
    {
        ScopePolicy::assertMatches($this->class, $this->scope);

        if ($this->serial === null) {
            throw new LogicException('A serial must be supplied via withSerial(); the shell issues it atomically.');
        }

        return Identifier::mint($this->class, $this->serial, $this->scope, $this->width);
    }

    #[\NoDiscard('the returned command must be dispatched through an Action')]
    public function reserve(
        string $reason,
        ?string $reservedBy = null,
        ?DateTimeImmutable $expiresAt = null,
        ?SpecEdition $edition = null,
    ): Reserve {
        return new Reserve(
            identifier: $this->build(),
            reason: $reason,
            actor: $this->actor(),
            occurredAt: $this->occurredAt(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
            reservedBy: $reservedBy,
            expiresAt: $expiresAt,
            specEdition: $edition,
        );
    }

    #[\NoDiscard('the returned command must be dispatched through an Action')]
    public function commission(
        ?Alias $alias = null,
        string $description = '',
        ?SubjectRef $subject = null,
    ): Commission {
        return new Commission(
            identifier: $this->build(),
            actor: $this->actor(),
            occurredAt: $this->occurredAt(),
            correlationId: $this->correlationId(),
            idempotencyKey: $this->idempotencyKey(),
            alias: $alias,
            description: $description,
            subject: $subject,
        );
    }

    private function actor(): Actor
    {
        return $this->actor ?? throw new LogicException('Set the actor with by() before building a command.');
    }

    private function occurredAt(): DateTimeImmutable
    {
        return $this->occurredAt ?? throw new LogicException('Set the timestamp with at() before building a command.');
    }

    private function correlationId(): string
    {
        return $this->correlationId ?? throw new LogicException('Set the correlation id with correlatedBy() first.');
    }

    private function idempotencyKey(): string
    {
        return $this->idempotencyKey ?? throw new LogicException('Set the idempotency key with idempotentWith() first.');
    }
}
