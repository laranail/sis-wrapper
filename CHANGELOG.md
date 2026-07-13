# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html).

This is the Laravel wrapper for the Simtabi Identifier System. It consumes the pure, framework-free SDK
`simtabi/sis-sdk` and is developed and versioned independently.

## [Unreleased]

## [0.1.0] - 2026-07-13

### Added

- The Laravel 13 binding for SIS, consuming `simtabi/sis-sdk`: a config-driven register whose whole
  vocabulary (issuer, classes, subtypes, reserved aliases, serials, environments) lives in
  `config/sis.php`, an enforced morph map, an immutable append-only register backed by storage-layer
  triggers, the Controller → Action → registrar-decorator stack over the SDK decider, a transactional
  outbox with at-least-once relay, deny-by-default pluggable RBAC behind a `PermissionResolver` seam,
  keyed idempotency, a headless JSON API with RFC 9457 problem+json, the `Sis` facade, SSRF-guarded
  signed webhooks with a per-endpoint circuit breaker, factories and seeders, and the
  `laranail::sis-wrapper.*` (aliased `sis:*`) Artisan commands.
- Built on the laranail toolkits: **package-tools** (a single `PackageServiceProvider`), **console** (the
  command base), **enumerator** (ability labels), and **toolkit** (the reusable morph-alias registry).
- Native Laravel translations: every user-facing string (validation rule messages, console output, the
  capacity notification, RFC 9457 problem titles) is served from `resources/lang/` under the `sis::`
  namespace (also `laranail/sis-wrapper::`), publishable to the app's `lang/vendor/`.
- Full audit of denied authorization attempts (verdict `denied`) alongside the ability and verdict
  recorded on every applied effect, through one append-only, hash-chained audit writer.

### Changed

- Factories and seeders now live under `database/factories` and `database/seeders` (Laravel's
  conventional home), keeping the `Simtabi\Laranail\SIS\Database\{Factories,Seeders}` namespaces.

[Unreleased]: https://github.com/laranail/sis-wrapper/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/laranail/sis-wrapper/releases/tag/v0.1.0
