# The Artisan commands

Three operator commands ship with the package. Each carries the laranail convention name
`laranail::sis-wrapper.<command>` as its canonical name, with a short `sis:<command>` alias for everyday use —
both dispatch to the same command.

| Canonical name | Short alias |
|----------------|-------------|
| `laranail::sis-wrapper.install` | `sis:install` |
| `laranail::sis-wrapper.doctor` | `sis:doctor` |
| `laranail::sis-wrapper.permissions` | `sis:permissions` |

> The `::` in the canonical name carries an empty namespace segment that Symfony's validator would normally
> reject; the `SupportsNamespacedNames` trait from `laranail/console` writes it past the validator, and the
> command still dispatches because Symfony resolves an exact name before its `:`-splitting lookup.

The examples below use the short alias; the canonical name works identically.

## `sis:install`

Publishes config and migrations, runs the migrations, and finishes by running the doctor — the one-liner after `composer require`.

```bash
php artisan sis:install
php artisan sis:install --force     # overwrite already-published config/migrations
```

It runs, in order: `vendor:publish --tag=laranail::sis-wrapper-config`, `vendor:publish --tag=laranail::sis-wrapper-migrations`, `migrate`, then `sis:doctor` (whose exit code becomes the install's). Zero required config to start.

## `sis:doctor`

The health check — the first thing to run when something is wrong, and the spine of the runbook. It reports each check as `[OK]` / `[WARN]` / `[FAIL]` and exits non-zero if any check is a hard failure.

| # | Check | Result |
|---|-------|--------|
| 1 | Schema present (`register`, `audit`, `outbox`, `idempotency_keys`, `serials`) | FAIL if any table is missing |
| 2 | Storage-layer triggers supported on the driver | WARN on a driver that cannot enforce them (e.g. SQLite) |
| 3 | Check characters verify across a sample | FAIL on corrupt identifiers |
| 4 | Every stored subject alias resolves through the morph map | FAIL on unknown morph aliases |
| 5 | Outbox drained | WARN on pending messages |
| 6 | Capacity headroom across all serial spaces | WARN near exhaustion |
| 7 | Admin panels detected (informational) | OK — reports which panels the register presenter can bind to, or "headless" |

```bash
php artisan sis:doctor
```

A hard failure (missing schema, corrupt identifiers, unresolvable morphs) exits `FAILURE`; warnings alone exit `SUCCESS`.

## `sis:permissions`

Lists the ability set and the configured resolver, and — with `--actor` — exactly what an actor may do. Run this when "it says 403 and I don't know why".

```bash
php artisan sis:permissions                    # ability list + current resolver
php artisan sis:permissions --actor=user:1     # per-ability [allow]/[deny] for user:1
```

The `--actor` value is `type:id` (e.g. `user:1`); each of the fourteen `SisAbility` values is checked against the resolver and printed `[allow]` or `[deny]`. See [authorization](authorization.md).

## Scheduled jobs (not commands)

Recurring maintenance runs as scheduled jobs registered by `SisServiceProvider::packageBooted()`, not as invokable commands — `RelayOutbox`, `ReapLapsedReservations`, `ReportSerialCapacity`, `VerifyRegisterIntegrity`, `DetectOrphanedSubjects`, and `PruneIdempotencyKeys`. Each is individually disableable in [`config('sis.schedule')`](../configuration.md#schedule).

---

[← Docs index](../../README.md#documentation)
