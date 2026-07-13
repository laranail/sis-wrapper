# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This monorepo ships two packages from one tag: the pure core `simtabi/sis` (`src/Core`) and its
Laravel binding `laranail/sis-wrapper` (`src/Laravel`).

## [Unreleased]

## [0.1.0] - 2026-07-13

### Added

- **`simtabi/sis`** — the pure-PHP, zero-dependency functional core and reference implementation of
  `SIM-STD-0001:2026`: the identifier grammar (Form G/S), ISO 7064 MOD 1271-36 check characters, the
  22-class register, the lifecycle state machine, alias derivation ("widen before mangle"), semver
  release ordering, and a pure `decide(Command, Snapshot): Decision` decider with a conformance suite.
- **`laranail/sis-wrapper`** — the Laravel 13 binding: an enforced morph map, an immutable append-only
  register backed by storage-layer triggers, the Controller → Action → Service → registrar-decorator
  stack, a transactional outbox with at-least-once relay, deny-by-default pluggable RBAC behind a
  `PermissionResolver` seam, keyed idempotency, a headless JSON API with RFC 9457 problem+json, the
  `Sis` facade, SSRF-guarded signed webhooks with a per-endpoint circuit breaker, and the `sis:*`
  Artisan commands.

[Unreleased]: https://github.com/laranail/sis/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/laranail/sis/releases/tag/v0.1.0
