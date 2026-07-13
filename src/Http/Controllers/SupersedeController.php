<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\SupersedeIdentifier;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Http\Controllers\Concerns\ResolvesIdentifier;
use Simtabi\Laranail\SIS\Http\Requests\SupersedeIdentifierRequest;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;

/** POST identifiers/{identifier}/supersede — record that a successor replaces this identifier (§8), never editing it. */
final class SupersedeController
{
    use ResolvesIdentifier;

    public function __construct(
        private readonly SupersedeIdentifier $supersede,
        private readonly ActorResolver $actors,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $identifier, SupersedeIdentifierRequest $request): JsonResponse
    {
        $parsed = $this->identifier($identifier);

        ($this->supersede)($parsed, $request->successor(), $request->context($this->actors->current()));

        $record = $this->read->find($parsed);

        if ($record === null) {
            abort(404);
        }

        return (new IdentifierResource($record))->response();
    }
}
