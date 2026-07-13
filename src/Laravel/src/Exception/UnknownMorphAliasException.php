<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Exception;

use Simtabi\SIS\Exception\SisLogicException;

/**
 * A morph alias (or a model) that is not in the governed map. This is a CRITICAL failure, not a 422 typo:
 * the code is about to write an unresolvable pointer into an immutable, never-deleted row. The shell
 * exception extends the core category, so a consumer's `catch (SisException)` still holds.
 */
final class UnknownMorphAliasException extends SisLogicException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026 §2.5 (morph map)';

    public static function forAlias(string $alias): self
    {
        return new self(
            sprintf('Morph alias "%s" is not in the SIS morph map. Add it to config(\'sis.morph.aliases\').', $alias),
            ['operation' => 'resolve-subject', 'alias' => $alias],
        );
    }

    public static function forClass(string $class): self
    {
        return new self(
            sprintf('Model %s is not mapped to a SIS morph alias. Add it to config(\'sis.morph.aliases\').', $class),
            ['operation' => 'resolve-subject', 'class' => $class],
        );
    }
}
