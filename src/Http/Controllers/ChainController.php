<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Simtabi\Laranail\SIS\Actions\TraceSupersessionChain;
use Simtabi\Laranail\SIS\Http\Controllers\Concerns\ResolvesIdentifier;
use Simtabi\Laranail\SIS\Read\SisReadModel;
use Simtabi\SIS\Identifier\Identifier;

/** GET identifiers/{identifier}/chain — walk the supersession chain (§8), cycle-safe, terminal successor last. */
final class ChainController
{
    use ResolvesIdentifier;

    public function __construct(
        private readonly TraceSupersessionChain $trace,
        private readonly SisReadModel $read,
    ) {}

    public function __invoke(string $identifier): JsonResponse
    {
        $parsed = $this->identifier($identifier);

        if ($this->read->find($parsed) === null) {
            abort(404);
        }

        $chain = ($this->trace)($parsed);

        return new JsonResponse([
            'identifier' => (string) $parsed,
            'chain' => array_map(static fn (Identifier $id): string => (string) $id, $chain),
            'terminal' => (string) $this->trace->terminal($parsed),
        ]);
    }
}
