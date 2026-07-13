<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Morph;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\SIS\Exception\UnknownMorphAliasException;

/**
 * The governed alias <-> model-class map (§2.5). Allocated once, never reassigned, retired with the thing
 * it names — the same discipline as the class register. An unknown alias or class is a critical failure,
 * never a silently stored string.
 */
final class MorphAliasRegistry
{
    /** @var array<string, class-string<Model>> alias => model class */
    private array $aliasToClass = [];

    /** @var array<class-string<Model>, string> model class => alias */
    private array $classToAlias = [];

    /** @param array<string, class-string<Model>> $map alias => model class */
    public function __construct(array $map)
    {
        foreach ($map as $alias => $class) {
            $this->aliasToClass[$alias] = $class;
            $this->classToAlias[$class] = $alias;
        }
    }

    /** @return array<string, class-string<Model>> alias => model class */
    public function map(): array
    {
        return $this->aliasToClass;
    }

    public function aliasFor(string $class): string
    {
        return $this->classToAlias[$class] ?? throw UnknownMorphAliasException::forClass($class);
    }

    /** @return class-string<Model> */
    public function classFor(string $alias): string
    {
        return $this->aliasToClass[$alias] ?? throw UnknownMorphAliasException::forAlias($alias);
    }

    public function hasAlias(string $alias): bool
    {
        return isset($this->aliasToClass[$alias]);
    }

    public function hasClass(string $class): bool
    {
        return isset($this->classToAlias[$class]);
    }

    public function isEmpty(): bool
    {
        return $this->aliasToClass === [];
    }
}
