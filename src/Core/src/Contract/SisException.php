<?php

declare(strict_types=1);

namespace Simtabi\SIS\Contract;

use Throwable;

/**
 * The marker every SIS failure implements. Consumers catch this to trap anything the register throws,
 * regardless of which layer raised it — the shell's exceptions extend the core categories, so a
 * `catch (SisException)` still holds.
 *
 * Every implementation carries structured context (Part II rule 13) and cites the specification clause
 * it enforces, so a developer who hits one is handed the rule, not a shrug. The original cause is
 * preserved as `previous` and is never flattened into the message; secrets and PII are never placed in
 * the context (Part II rule 15).
 */
interface SisException extends Throwable
{
    /**
     * Structured, redacted context for the central handler and the audit row.
     *
     * @return array<string, mixed>
     */
    public function context(): array;

    /** The clause of SIM-STD-0001:2026 this failure enforces, e.g. "SIM-STD-0001:2026 §6.3". */
    public function specClause(): string;

    /** "critical" (fail fast, every environment) or "degradable" (report and continue). */
    public function criticality(): string;
}
