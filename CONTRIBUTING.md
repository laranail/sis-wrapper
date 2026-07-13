# Contributing

Thanks for your interest in improving the SIS Laravel wrapper. This package (`laranail/sis-wrapper`) is the
Laravel 13 binding for the Simtabi Identifier System; it consumes the pure, framework-free SDK
`simtabi/sis-sdk` (a separate repository). The identifier grammar, check characters, and register logic live
in the SDK — this repo is the Eloquent / HTTP / console shell around it.

## Ground rules

- **The specification is authoritative.** `SIM-STD-0001:2026` (in the SDK repo) governs the grammar, check
  characters, class register, and lifecycle. A change to any of those is a specification amendment made in
  the SDK, not a feature here — raise it as such.
- **The dependency direction is one-way.** The wrapper depends on `simtabi/sis-sdk`; the SDK must never
  depend back on the wrapper. `deptrac` enforces the boundary and fails the build if you cross it. Register
  vocabulary is config, not code — it lives in `config/sis.php`, never hardcoded in the shell.
- **Defence in depth is deliberate.** The SDK's core preconditions are advisory; the database constraints and
  triggers are authoritative. Do not remove one because the other exists.
- **No AI/assistant attribution** in commits or pull requests.

## Local setup

The monorepo resolves its sibling laranail packages (`laranail/console`, `laranail/package-tools`) through
path repositories at `../console` and `../package/tools`. Check those out next to this repo, then:

```bash
composer install
```

Tests run against SQLite; no database server is needed locally. The PostgreSQL trigger tests run in CI only.

## The quality gate

Every change must pass the full gate before it is merged:

```bash
composer quality      # parallel-lint, Pint, deptrac, PHPStan (core + shell), PHPUnit
```

Individually:

```bash
composer lint          # php-parallel-lint
composer pint          # Pint, --test (style check); `composer pint:fix` to apply
composer deptrac       # architecture boundary
composer analyse       # PHPStan level 10 (core) + Larastan level 9 (shell), no baseline
composer test          # PHPUnit — the full core + shell suite
composer rector        # Rector, dry run
```

## Tests

- New behaviour needs tests. The core ships a **conformance suite**; the Eloquent register is held to it, so
  the shell can never silently drift from the pure decider.
- Prefer a failing test that demonstrates the bug, then the fix.
- Use realistic example identifiers (`SIM-CLT-100001-9O`, alias `ADIQ`) for readable fixtures.

## Commits and pull requests

- Subject line ≤ 72 characters, imperative mood. The body explains *why*, not *what*.
- Keep each PR focused; unrelated changes belong in separate PRs.
- Fill in the pull-request template checklist.

## Reporting security issues

Do not open a public issue. Follow [SECURITY.md](SECURITY.md).
