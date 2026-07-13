<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\CommissionIdentifier;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Http\Controllers\Concerns\ResolvesIdentifier;
use Simtabi\Laranail\SIS\Http\Requests\CommissionIdentifierRequest;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;

/** POST identifiers/{identifier}/commission — lock a reserved identifier forever, optionally binding its alias and subject. */
final class CommissionController
{
    use ResolvesIdentifier;

    public function __construct(
        private readonly CommissionIdentifier $commission,
        private readonly ActorResolver $actors,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $identifier, CommissionIdentifierRequest $request): JsonResponse
    {
        $this->identifier($identifier);

        $committed = ($this->commission)($request->toData($this->actors->current()));

        $record = $this->read->find($committed);

        if ($record === null) {
            abort(500);
        }

        return (new IdentifierResource($record))->response();
    }
}
