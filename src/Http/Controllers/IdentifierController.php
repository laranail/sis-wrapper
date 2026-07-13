<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\ReserveIdentifier;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Http\Requests\ReserveIdentifierRequest;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Contract\SisEngine;

/**
 * Thin: a FormRequest in, an Action call, a Resource out. A controller with a rule in it is a bug. The
 * write requires an Idempotency-Key (middleware) and is authorized by the registrar stack.
 */
final class IdentifierController
{
    public function __construct(
        private readonly ReserveIdentifier $reserve,
        private readonly ActorResolver $actors,
        private readonly SisReadModel $read,
        private readonly SisEngine $engine,
    ) {}

    public function store(ReserveIdentifierRequest $request): JsonResponse
    {
        $identifier = ($this->reserve)($request->toData($this->actors->current()));

        $record = $this->read->find($identifier);

        if ($record === null) {
            abort(500);
        }

        return (new IdentifierResource($record))->response()->setStatusCode(201);
    }

    public function show(string $identifier): JsonResponse
    {
        if (!$this->engine->validate($identifier)) {
            abort(404);
        }

        $record = $this->read->find($this->engine->parse($identifier));

        if ($record === null) {
            abort(404);
        }

        return (new IdentifierResource($record))->response();
    }
}
