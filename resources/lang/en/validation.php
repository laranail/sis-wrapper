<?php

declare(strict_types=1);

return [
    'unknown_morph_alias' => 'The :attribute is not a known SIS morph alias (SIM-STD-0001:2026 §2.5).',
    'invalid_alias' => 'The :attribute is not a valid alias (SIM-STD-0001:2026 §5.1).',
    'reserved_alias' => 'The :attribute is a reserved alias (SIM-STD-0001:2026 §5.3).',
    'alias_taken' => 'The :attribute is already taken (SIM-STD-0001:2026 §5).',
    'reserved_alias_not_allocatable' => 'The :attribute is a reserved alias and cannot be allocated (SIM-STD-0001:2026 §5.3).',
    'form_s_requires_scope' => ':class is a Form S class and requires a scope (SIM-STD-0001:2026 §2, §3).',
    'form_g_takes_no_scope' => ':class is a Form G class and takes no scope (SIM-STD-0001:2026 §2, §3).',
    'invalid_mnemonic_alias' => 'The :attribute is not a valid mnemonic alias: it must match [A-Z][A-Z0-9]{3,5} (SIM-STD-0001:2026 §5.1).',
    'invalid_identifier_of_class' => 'The :attribute is not a valid :class identifier (SIM-STD-0001:2026 §3).',
    'illegal_transition' => 'The :attribute is not a legal transition from :from (SIM-STD-0001:2026 §6.2).',
    'invalid_identifier' => 'The :attribute is not a valid SIS/1 identifier — its grammar or check characters do not verify (SIM-STD-0001:2026 §2, §4).',
    'invalid_semver' => 'The :attribute is not a valid SIS/1 release version (SIM-STD-0001:2026 §7.2).',
    'invalid_subtype' => 'The :attribute is not a permitted subtype for :class (SIM-STD-0001:2026 §3.7).',
];
