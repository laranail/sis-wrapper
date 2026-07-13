<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Simtabi\Laranail\Toolkit\Morph\MorphAliasRegistry;
use Simtabi\SIS\Identifier\Actor;
use Simtabi\SIS\Identifier\SubjectRef;
use Throwable;

/**
 * Maps an authenticated model to an Actor, and produces the non-human actors: the scheduler, a console
 * operator, an API client, a queued job. Every command has an actor — "the system did it" is an answer;
 * "nobody knows" is not. A guest actor denies every stateful ability.
 */
final class ActorResolver
{
    public function __construct(
        private readonly MorphAliasRegistry $morphs,
    ) {}

    public function current(): Actor
    {
        $user = Auth::user();

        if ($user instanceof Model) {
            try {
                return self::toActor($this->subjectRefFor($user));
            } catch (Throwable) {
                // An unmapped user falls back to guest rather than leaking a class name.
            }
        }

        return $this->guest();
    }

    public function forModel(Model $model): Actor
    {
        return self::toActor($this->subjectRefFor($model));
    }

    /** The subject reference for a model — its governed morph alias plus its key — via the toolkit registry. */
    private function subjectRefFor(Model $model): SubjectRef
    {
        return SubjectRef::of(...$this->morphs->aliasAndKeyFor($model));
    }

    public function guest(): Actor
    {
        return Actor::of('guest', 'anonymous');
    }

    public function system(): Actor
    {
        return Actor::of('system', 'scheduler');
    }

    public function console(string $operator = 'operator'): Actor
    {
        return Actor::of('console', $operator);
    }

    private static function toActor(SubjectRef $ref): Actor
    {
        return Actor::of($ref->type, $ref->id);
    }
}
