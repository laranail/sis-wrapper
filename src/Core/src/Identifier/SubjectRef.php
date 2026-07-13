<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

/**
 * The thing an identifier names — a morph ALIAS plus an id. It is a value, not a model: the core never
 * sees a fully-qualified class name. A raw FQCN written into an immutable, never-deleted row is a time
 * bomb, so the mapping to a class is the shell's job and the alias is what crosses the boundary.
 */
final readonly class SubjectRef
{
    private function __construct(
        public string $type,
        public string $id,
    ) {}

    public static function of(string $type, string $id): self
    {
        return new self($type, $id);
    }

    public function reference(): string
    {
        return $this->type . ':' . $this->id;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}
