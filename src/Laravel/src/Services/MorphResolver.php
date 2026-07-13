<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Simtabi\Laranail\SIS\Exception\UnknownMorphAliasException;
use Simtabi\Laranail\SIS\Morph\MorphAliasRegistry;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * Model <-> morph alias, and the guard against unknown aliases. The core only ever sees the alias (a
 * string); turning that into a model, and a model into an alias, is the shell's job.
 */
final class MorphResolver
{
    public function __construct(
        private readonly MorphAliasRegistry $registry,
    ) {}

    /** The subject reference for a model — its governed alias plus its key. Fails if the model is unmapped. */
    public function subjectRefFor(Model $model): SubjectRef
    {
        $key = $model->getKey();

        if (!is_scalar($key)) {
            throw new RuntimeException('A SIS subject model must have a scalar primary key.');
        }

        return SubjectRef::of($this->registry->aliasFor($model::class), (string) $key);
    }

    /** Resolve a subject reference back to its model, or null if the row is gone. */
    public function resolve(SubjectRef $subject): ?Model
    {
        $class = $this->registry->classFor($subject->type);

        return $class::query()->find($subject->id);
    }

    public function assertKnownAlias(string $alias): void
    {
        if (!$this->registry->hasAlias($alias)) {
            throw UnknownMorphAliasException::forAlias($alias);
        }
    }
}
