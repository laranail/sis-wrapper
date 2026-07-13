<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Services;

use Illuminate\Database\Eloquent\Model;
use Simtabi\Laranail\SIS\Exception\UnknownMorphAliasException;
use Simtabi\Laranail\Toolkit\Morph\Exceptions\UnknownMorphAliasException as ToolkitUnknownMorphAliasException;
use Simtabi\Laranail\Toolkit\Morph\MorphAliasRegistry;
use Simtabi\SIS\Identifier\SubjectRef;

/**
 * A thin, SIS-specific adapter over the reusable toolkit {@see MorphAliasRegistry}. It speaks the SDK's
 * {@see SubjectRef} value object (a `{type,id}` pair) at the boundary, while the generic alias <-> model
 * map and resolution live in the toolkit. The toolkit's framework-light exception is re-thrown as the
 * wrapper's own {@see UnknownMorphAliasException} so the SIS error taxonomy (a critical `SisLogicException`
 * rendered as RFC 9457 problem+json) still holds for a consumer catching `SisException`.
 */
final class MorphResolver
{
    public function __construct(
        private readonly MorphAliasRegistry $registry,
    ) {}

    /** The subject reference for a model — its governed alias plus its key. Fails if the model is unmapped. */
    public function subjectRefFor(Model $model): SubjectRef
    {
        try {
            return SubjectRef::of(...$this->registry->aliasAndKeyFor($model));
        } catch (ToolkitUnknownMorphAliasException) {
            throw UnknownMorphAliasException::forClass($model::class);
        }
    }

    /** Resolve a subject reference back to its model, or null if the row is gone. */
    public function resolve(SubjectRef $subject): ?Model
    {
        try {
            return $this->registry->resolve($subject->type, $subject->id);
        } catch (ToolkitUnknownMorphAliasException) {
            throw UnknownMorphAliasException::forAlias($subject->type);
        }
    }

    public function assertKnownAlias(string $alias): void
    {
        try {
            $this->registry->assertKnownAlias($alias);
        } catch (ToolkitUnknownMorphAliasException) {
            throw UnknownMorphAliasException::forAlias($alias);
        }
    }
}
