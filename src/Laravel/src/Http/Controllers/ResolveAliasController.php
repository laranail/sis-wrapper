<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\ResolveAlias;
use Simtabi\Laranail\SIS\Http\Resources\IdentifierResource;
use Simtabi\Laranail\SIS\Read\SisReadModel;

/** GET aliases/{alias} — resolve a mnemonic alias (ADIQ, MALISA) to its canonical identifier record (§5). */
final class ResolveAliasController
{
    public function __construct(
        private readonly ResolveAlias $resolve,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $alias): JsonResponse
    {
        $identifier = ($this->resolve)($alias);

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
