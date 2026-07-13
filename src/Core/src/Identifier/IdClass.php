<?php

declare(strict_types=1);

namespace Simtabi\SIS\Identifier;

/**
 * The SIS/1 class register — SIM-STD-0001:2026 §3.
 *
 * A class is a namespace: SIM-INV-… can never be confused with SIM-PRS-…. Class codes are allocated by
 * the specification and are NEVER reassigned; a retired class retires its code with it. This is a native
 * PHP enum by design — the check algorithm, grammar, class register, and state machine are the guarantee
 * and must not be extensible.
 */
enum IdClass: string
{
    // Party and organisation (§3.1)
    case Client = 'CLT';
    case Person = 'PRS';
    case Vendor = 'VND';
    case Department = 'DPT';

    // Commercial (§3.2)
    case Project = 'PRJ';
    case Sow = 'SOW';
    case ChangeOrder = 'CHG';
    case Milestone = 'MIL';
    case Quote = 'QUO';
    case Invoice = 'INV';
    case CreditNote = 'CRN';

    // Product (§3.3)
    case Product = 'PRD';
    case Service = 'SVC';
    case Component = 'CMP';
    case Release = 'REL';

    // Asset and governance (§3.4)
    case Asset = 'AST';
    case Document = 'DOC';
    case Standard = 'STD';
    case Decision = 'ADR';

    // Operations (§3.5)
    case Ticket = 'TKT';
    case Incident = 'INC';
    case Environment = 'ENV';

    /**
     * Form S (scoped) identifiers belong to a client and carry its alias. Form G (global) identifiers
     * belong to Simtabi.
     */
    public function isScoped(): bool
    {
        return match ($this) {
            self::Project, self::Sow, self::ChangeOrder, self::Milestone,
            self::Quote, self::Invoice, self::CreditNote, self::Document,
            self::Ticket, self::Environment => true,
            default => false,
        };
    }

    /**
     * The first serial for this class.
     *
     * Global serials start at 100001 so the sequence never advertises how many people, products, or
     * clients Simtabi has. Scoped serials start at 1 because a client already knows how many invoices it
     * has received. STD is the deliberate exception (§3.4): a global class that starts at 1, because
     * Simtabi issues few standards and there is nothing to hide.
     */
    public function serialStart(): int
    {
        if ($this === self::Standard) {
            return 1;
        }

        return $this->isScoped() ? 1 : 100001;
    }

    /** Classes whose entities carry a human-facing mnemonic alias (§5). */
    public function usesAlias(): bool
    {
        return match ($this) {
            self::Client, self::Product, self::Service,
            self::Component, self::Department => true,
            default => false,
        };
    }

    /**
     * The controlled subtype vocabulary for this class (§3.7), or an empty list if the class carries no
     * subtype. A subtype is an ATTRIBUTE in the register's `subtype` column, never a segment of the
     * identifier.
     *
     * @return list<string>
     */
    public function subtypes(): array
    {
        return match ($this) {
            self::Asset => ['LAP', 'MON', 'PHN', 'SRV', 'LIC', 'DOM', 'KEY', 'MSC'],
            self::Document => ['ICA', 'MSA', 'SOW', 'NDA', 'CHG', 'DPA', 'IPA', 'EMP', 'QUO'],
            self::Person => ['ENG', 'DES', 'PM', 'OPS', 'BIZ', 'EXE'],
            self::Department => ['ENG', 'DES', 'OPS', 'BIZ', 'FIN', 'EXE'],
            default => [],
        };
    }

    /** Whether this class defines any subtype vocabulary at all. */
    public function hasSubtypeVocabulary(): bool
    {
        return $this->subtypes() !== [];
    }

    /**
     * Whether $subtype is a permitted subtype for this class.
     *
     * A class with no vocabulary permits NO subtype — its `subtype` column must be null. This matches the
     * register's `subtype_vocabulary` CHECK exactly; the two enforcement layers must never disagree.
     */
    public function permitsSubtype(string $subtype): bool
    {
        return in_array(strtoupper($subtype), $this->subtypes(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::Client => 'Client',
            self::Person => 'Person',
            self::Vendor => 'Vendor',
            self::Department => 'Department',
            self::Project => 'Project',
            self::Sow => 'Statement of Work',
            self::ChangeOrder => 'Change Order',
            self::Milestone => 'Milestone',
            self::Quote => 'Quote',
            self::Invoice => 'Invoice',
            self::CreditNote => 'Credit Note',
            self::Product => 'Product',
            self::Service => 'Service',
            self::Component => 'Component',
            self::Release => 'Release',
            self::Asset => 'Asset',
            self::Document => 'Document',
            self::Standard => 'Standard',
            self::Decision => 'Decision Record',
            self::Ticket => 'Ticket',
            self::Incident => 'Incident',
            self::Environment => 'Environment',
        };
    }
}
