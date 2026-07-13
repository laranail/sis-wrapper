# laranail/sis-monorepo

Development monorepo for the Simtabi Identifier System (SIS) — the reference implementation of `SIM-STD-0001:2026`.

> This repository is **not published**. It is a development harness (`"type": "project"`, name `laranail/sis-monorepo`) that holds two packages, developed together and split to their own repos via `git subtree`.

## What is in here

| Path | Package | What it is |
|------|---------|------------|
| `src/Core` | [`simtabi/sis`](src/Core/README.md) | The pure-PHP, zero-runtime-dependency functional core: grammar, ISO 7064 check characters, the class register, the lifecycle state machine, alias derivation, and semver releases. Namespace `Simtabi\SIS\`. |
| `src/Laravel` | [`laranail/sis-wrapper`](src/Laravel/README.md) | The Laravel 13 imperative shell: an immutable append-only register with storage-layer triggers, actions and services, a transactional outbox, deny-by-default pluggable RBAC, and a headless JSON API. Namespace `Simtabi\Laranail\SIS\`. |

The split is one-directional: `src/Core` becomes `github.com/simtabi/sis`, `src/Laravel` becomes `github.com/laranail/sis-wrapper`. Consumers install the published packages; nobody depends on this monorepo.

## The specification

The normative spec — `SIM-STD-0001:2026`, the Simtabi Identifier Specification (SIS/1) — is checked in at [`SIM-STD-0001-2026.md`](SIM-STD-0001-2026.md). Where the code and the spec disagree, the spec is normative and the code is defective. Read it for domain accuracy (grammar Form G/S, the 22-class register, the ISO 7064 check, the lifecycle, aliases, and supersession).

## Dev harness

The two packages are wired together with `path` repositories (symlinked) so a change in `src/Core` is seen immediately in `src/Laravel`. All tooling runs from the repo root.

```bash
composer install          # installs both packages plus dev tooling

composer test             # PHPUnit — the whole suite
composer test:core        # the core testsuite only
composer test:shell       # the Laravel testsuite only

composer quality          # lint · pint · deptrac · phpstan (core + shell) · phpunit
```

Individual quality gates: `composer lint`, `composer pint`, `composer deptrac`, `composer analyse` (`analyse:core` / `analyse:shell`), `composer rector`. Static analysis is split so the core is checked with no framework on the classpath (`phpstan-core.neon`) and the shell with Larastan (`phpstan-shell.neon`); `deptrac.yaml` enforces the core-cannot-see-the-shell boundary.

## Documentation

The system documentation lives in [`docs/`](docs/) and is hosted per package:

- `simtabi/sis` — https://opensource.simtabi.com/documentation/simtabi/sis/
- `laranail/sis-wrapper` — https://opensource.simtabi.com/documentation/laranail/sis-wrapper/

The headless JSON API is described by [`openapi.yaml`](openapi.yaml) (OpenAPI 3.1).

## License

MIT. Copyright (c) 2026 Simtabi LLC. See [`LICENSE`](LICENSE).
