<?php

declare(strict_types=1);

namespace Simtabi\SIS\Exception;

use RuntimeException;
use Simtabi\SIS\Contract\SisException;
use Throwable;

/**
 * The base every SIS exception extends. It carries structured context and a cited spec clause, and it
 * preserves the original cause. Subclasses declare their spec clause and criticality via the class
 * constants; the category subclasses (`SisLogicException`, `SisStateException`, …) exist so a consumer
 * can catch a whole family, and the shell's exceptions extend those same categories.
 *
 * Context MUST NOT carry secrets or PII (Part II rule 15). Identifiers, states, and actor *references*
 * are business data and may appear; credentials, personal names, and emails never do.
 */
abstract class SisExceptionBase extends RuntimeException implements SisException
{
    protected const string SPEC_CLAUSE = 'SIM-STD-0001:2026';

    protected const string CRITICALITY = 'critical';

    /** @param array<string, mixed> $context */
    public function __construct(
        string $message,
        private readonly array $context = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function specClause(): string
    {
        return static::SPEC_CLAUSE;
    }

    public function criticality(): string
    {
        return static::CRITICALITY;
    }

    public function context(): array
    {
        $previous = $this->getPrevious();

        return [
            'exception' => static::class,
            'criticality' => $this->criticality(),
            'spec_clause' => $this->specClause(),
            'cause_type' => $previous !== null ? $previous::class : null,
            ...$this->context,
        ];
    }
}
