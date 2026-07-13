<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

/**
 * Who performed a command — a stable REFERENCE (a morph alias plus an id), never a name and never an
 * email (Part II rule 15). Every command has an actor; "the system did it" is an answer, "nobody knows"
 * is not. The shell's ActorResolver produces the non-human actors (scheduler, console, API client, job);
 * the core only holds the reference.
 */
final readonly class Actor
{
    private function __construct(
        public string $type,
        public string $id,
    ) {}

    public static function of(string $type, string $id): self
    {
        return new self($type, $id);
    }

    /** A stable, PII-free reference such as "user:9" or "scheduler:system". */
    public function reference(): string
    {
        return $this->type . ':' . $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
