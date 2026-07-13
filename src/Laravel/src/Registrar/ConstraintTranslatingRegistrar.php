<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Registrar;

use Illuminate\Database\QueryException;
use Simtabi\Laranail\SIS\Contract\Registrar;
use Simtabi\Laranail\SIS\Error\ConstraintTranslator;
use Simtabi\SIS\Contract\Command;
use Simtabi\SIS\Decision\Decision;

/**
 * The database constraints and triggers are the authority. This catches their violations and rethrows the
 * SAME core exception the advisory precondition would have raised, so the caller sees one exception type
 * whether the race was lost at the check or at the commit. It sits OUTSIDE the transaction so it catches
 * failures surfaced at commit; a failure that is not ours is rethrown untouched.
 */
final class ConstraintTranslatingRegistrar implements Registrar
{
    public function __construct(
        private readonly Registrar $inner,
        private readonly ConstraintTranslator $translator,
    ) {}

    public function apply(Command $command): Decision
    {
        try {
            return $this->inner->apply($command);
        } catch (QueryException $e) {
            throw $this->translator->translate($e, $command) ?? $e;
        }
    }
}
