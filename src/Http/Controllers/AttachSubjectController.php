<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\AttachSubject;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Http\Controllers\Concerns\ResolvesIdentifier;
use Simtabi\Laranail\SIS\Http\Requests\AttachSubjectRequest;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;

/** POST identifiers/{identifier}/subject — bind the thing this reserved identifier names (§5, §9). */
final class AttachSubjectController
{
    use ResolvesIdentifier;

    public function __construct(
        private readonly AttachSubject $attach,
        private readonly ActorResolver $actors,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $identifier, AttachSubjectRequest $request): JsonResponse
    {
        $parsed = $this->identifier($identifier);

        ($this->attach)($parsed, $request->subject(), $request->context($this->actors->current()));

        $record = $this->read->find($parsed);

        if ($record === null) {
            abort(404);
        }

        return (new IdentifierResource($record))->response();
    }
}
