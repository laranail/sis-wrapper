<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\TransitionIdentifier;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Http\Controllers\Concerns\ResolvesIdentifier;
use Simtabi\Laranail\SIS\Http\Requests\TransitionIdentifierRequest;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;

/** POST identifiers/{identifier}/transition — suspend, restore, or decommission a commissioned identifier (§6.2). */
final class TransitionController
{
    use ResolvesIdentifier;

    public function __construct(
        private readonly TransitionIdentifier $transition,
        private readonly ActorResolver $actors,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $identifier, TransitionIdentifierRequest $request): JsonResponse
    {
        $parsed = $this->identifier($identifier);

        $this->transition->to($parsed, $request->targetState(), $request->context($this->actors->current()));

        $record = $this->read->find($parsed);

        if ($record === null) {
            abort(404);
        }

        return (new IdentifierResource($record))->response();
    }
}
