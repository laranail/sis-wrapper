<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Simtabi\Laranail\SIS\Actions\ResolveSubject;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Identifier\SubjectRef;

/** GET subjects?type=&id= — reverse lookup: which identifier names this thing? (§2.5). Authorized like a write (§2.13). */
final class ResolveSubjectController
{
    public function __construct(
        private readonly ResolveSubject $resolve,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $id = $request->query('id');

        if (!is_string($type) || !is_string($id) || $type === '' || $id === '') {
            abort(422);
        }

        $identifier = ($this->resolve)(SubjectRef::of($type, $id));

        if ($identifier === null) {
            abort(404);
        }

        $record = $this->read->find($identifier);

        if ($record === null) {
            abort(404);
        }

        return (new IdentifierResource($record))->response();
    }
}
