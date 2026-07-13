# SIS — Phase 2: Design

The design for `simtabi/sis` (pure core, `Simtabi\SIS\`) + `laranail/sis-wrapper` (Laravel 13 shell,
`Simtabi\Laranail\SIS\`), developed as one monorepo (`laranail/sis`) and split by subtree. This
document follows the Phase 1 audit; it decides the shape and resolves every deferred decision with a
recommendation. No package code is written in this phase. Work stops after this document for review,
before Phase 3 (build).

Precedence, when documents conflict: (1) `SIM-STD-0001:2026`, (2) the operating bible, (3) the
engineering reference Part II, (4) the `laranail/*` sources, (5) this prompt. Divergences are logged.

---

## A. Bible conformance (opens the design, per the prompt)

The bible outranks the prompt; the design satisfies its rules as follows.

- **README = slim pointer**, 4 plain-shields badges (Packagist · Tests · Static analysis · MIT),
  sentence-case headings, backticked class/command names, **no decorative emoji**; substantive content
  in `docs/`. Voice: Itch → Proof → Invite, ≤8-word taglines, the banned-words list enforced by Peck +
  review. Two published packages ⇒ two READMEs, each a pointer to the hosted docs at
  `opensource.simtabi.com/documentation/{simtabi|laranail}/{sis|sis-wrapper}/`.
- **Licence** MIT, `Copyright (c) 2026 Simtabi LLC`, SPDX `MIT`.
- **Identity** exact: `Simtabi LLC`; `opensource@simtabi.com` (SECURITY, CoC, generic OSS) /
  `imani@simtabi.com` (maintainer); commit `user.email` = the GitHub noreply.
- **`.github` health files** cascade (SECURITY, CODE_OF_CONDUCT, CONTRIBUTING, issue/PR templates);
  Dependabot weekly Mon 06:00 `America/New_York`.
- **laranail = Crimson** brand layer on brand surfaces only; package-repo badges stay plain.
- **No divergences from the bible** are required by this design. Where the prompt and bible both speak
  (badges, voice, docs tree, PHPStan-for-new-code), they agree.

---

## B. Laranail API surface (adopted)

Confirmed from source in Phase 1 (§2 of the audit). The shell adopts:

- **`laranail/package-tools ^0.1`** — `Providers\PackageServiceProvider` + the `Package` DSL
  (`configurePackage()` covers config, migrations, factories/seeders, commands, install command,
  scheduled commands, translations, routes, morph maps, observers, lifecycle hooks). `Commands\Command`
  + `SupportsNamespacedNames` for `laranail::sis.<command>` names. `InstallCommandDefinition`,
  `CronBuilder`.
- **`laranail/console ^0.1`** — base `Tools\Commands\Command`, `CommandServiceManager`
  (interaction/display/logger/error), `ConsoleWriter`, `Prompter`. Every `sis:*` command extends this.
  Raises the shell floor to `^8.4.1`; immaterial at 8.5.
- **`laranail/enumerator` — dropped.** One sentence, as the prompt asks: the core enums stay native and
  a native enum + a one-line Eloquent cast + our own spec-clause-citing `Rules/` cover labels, casting,
  and validation, so enumerator's reflection/cache surface earns nothing here.
- **`spatie/laravel-permission` — `suggest`, never `require`** (only when the Spatie resolver is used;
  `class_exists`-guarded).
- **Version verification:** the `orchestra/testbench` major paired with Laravel 13, and each Laravel 13
  / PHP 8.5 signature called, is confirmed against primary sources in **build-step-0** before any call
  is written. Not asserted here.

---

## C. Language and framework surface

**PHP 8.5, Laravel 13, nothing older.** Features used, and where — each confirmed against the changelog
at build-step-0 before use:

| Feature | Shipped | Use |
|---|---|---|
| Asymmetric visibility `public private(set)` | 8.4 (on the 8.5 baseline) | value objects & DTOs — public read, private write, no getter zoo |
| Property hooks | 8.4 | computed accessors on DTOs/value objects |
| `#[\Deprecated]` | 8.4 | the BC promise — deprecate & supersede, never redefine |
| Lazy objects | 8.4 | heavy read-model/service graphs most requests never touch |
| `clone with` | 8.5 | the fluent `Minter` and every immutable `Command`/`Data` |
| `#[\NoDiscard]` | 8.5 | `decide()`, `Identifier::mint()`, `SerialIssuer::next()` — returns that cost a serial or represent an unapplied write |
| `array_first()` / `array_last()` | 8.5 | `AliasPolicy::choose()` picks the first free candidate in rank order |
| Pipe operator `\|>` | 8.5 | only where it reads better than a variable; not in hot/debuggable paths |

Every use must survive "what did this make clearer or safer?" — no feature adopted to look current.

Laravel 13 APIs the shell calls (signatures verified at build-step-0): provider `register`/`boot`,
`Relation::requireMorphMap()` / `enforceMorphMap()`, `Schedule` via `afterResolving`, queue
`ShouldBeUnique`/`ShouldQueue` + `$backoff`/`$tries`/`uniqueId()`, custom `CastsAttributes`, `Rule`
objects (`ValidationRule`), `Gate`/policies, API Resources, route-model binding, `$exceptions->throttle()`
and the exception-handler hooks, `Notification` + channels.

---

## D. Resolved decisions (the audit's deferred items, decided)

1. **STD serial (audit §8.2).** Register STD identifiers are **Form G, 6-digit, serial starts 000001**;
   `serialStart()` returns 1 for STD. `SIM-STD-0001:2026` is an ISO-style *standard number*, not a SIS
   identifier (no check chars; predates the grammar) — documented in `docs/core/classes.md`.
2. **Spec home (audit §8.1).** Extract the spec verbatim to a normative `SIM-STD-0001-2026.md` at the
   repo root; publish a spec amendment updating §11's reference implementation from `simtabi/identifier`
   to `simtabi/sis`. (Governance action — flagged for the maintainer; the design assumes it.)
3. **Grandfathered legacy (audit §8.3).** Backfill accepts pre-SIS rows behind `spec_edition = 'pre-SIS'`,
   which bypasses grammar/check validation on read; such rows are never minted, only imported by
   `sis:backfill`.
4. **Morph map storage.** Config-declared map is **authoritative for resolution** (fast, versioned in
   code + `docs/laravel/morphs.md`); an append-only `sis_morph_aliases` table **records allocations for
   audit** and lets `sis:doctor` detect drift. Both, not either — config resolves, the table remembers.
5. **Audit hash-chaining.** Implemented (`Security\HashChain`), **default on** (security-first posture),
   config-toggleable for high-throughput consumers who accept losing tamper-evidence. Each `sis_audit`
   row carries `hash(content ‖ prev_hash)`; `sis:doctor` verifies the chain.
6. **Portable vs Postgres DDL.** Ship **Postgres + MySQL 8** trigger DDL, both preserving the §6.4
   storage-layer guarantee (MySQL via `BEFORE UPDATE` + `SIGNAL`). **SQLite is test/dev only**, with
   app-level enforcement and a **loud** boot-report + `sis:doctor` warning that the storage-layer
   guarantee is not present on this driver. Never a weaker guarantee under the same name.
7. **Unique subject.** Global `UNIQUE (subject_type, subject_id) WHERE subject_id IS NOT NULL` — "one
   thing, one identifier," the thesis. Rejected alternative (unique-per-class) documented in
   `docs/laravel/morphs.md` with the reason.
8. **`ext-intl` in the core.** Core `composer.json` requires **only `php: ^8.5`** (zero Composer deps —
   a security property); `ext-intl` and `ext-iconv` are `suggest`. `AliasPolicy` keeps the graceful
   transliteration fallback and documents the degraded path when neither extension is present.
9. **Decider shape.** **One decider class per command**, dispatched by a thin `Decider` facade using
   `match` on the command type. Per-command testability (each decider is a pure function with its own
   test file) plus a single typed entry point; the `match` is exhaustive so a new command that lacks a
   decider fails to compile-check under PHPStan.

---

## §2.1 The core, layer by layer

Three strictly one-directional layers; nothing inner knows anything outer. `final` + immutable
throughout; `declare(strict_types=1)`.

**Layer 1 — values** (`Identifier/`, `Version/`). Total, dependency-free. `Identifier`, `IdClass`,
`LifecycleState`, `Serial`, `Scope`, `Alias`, `CheckCharacters`, `Version`, `SpecEdition`, `Actor`
(a stable reference — morph alias + id, never a name/email), `SubjectRef` (morph **alias** + id — a
value; **the core never sees a class name**). Asymmetric visibility replaces getter walls.
`LifecycleState::canTransitionTo()` stays as-is.

**Layer 2 — policies** (`Policy/`). Pure rules over values, taking values in and returning values or
throwing — never asking a question they cannot answer from their arguments:

| Policy | Responsibility |
|---|---|
| `AliasPolicy` | `candidates(legalName): AliasCandidates` (pure ranking, "widen before mangle") + `choose(AliasCandidates, TakenAliases): Alias` (first free in rank order or `ExhaustedAliasSpaceException`) + the reserved-alias list |
| `SerialPolicy` | start (100001 global / 1 scoped / **1 for STD**), width 6–9, validate a supplied serial |
| `ScopePolicy` | scope required iff class is Form S; shape `[A-Z][A-Z0-9]{3,5}` |
| `TransitionPolicy` | wraps §6.2 legality + the §6.3 invariants as pure predicates |
| `SupersessionPolicy` | chain legality, cycle detection over a supplied chain snapshot |
| `CapacityPolicy` | how full a class+scope space is, and the threshold at which a human is told |
| `SubjectPolicy` | may this class name this kind of subject; is the subject already named (from snapshot) |

**Layer 3 — deciders** (`Decider/`). `decide(Command, Snapshot): Decision` — pure. One decider per
command, dispatched by `Decider::decide()` via `match`. `#[\NoDiscard]`.

**Supporting shapes.**
- **`Command/`** (immutable, `clone with`): everything the decision needs from the caller *and cannot
  fetch* — serial, `occurredAt` (time arrives as data → deterministic core tests), actor, subject ref,
  idempotency key, correlation id. Commands: `Reserve`, `Commission`, `Transition`, `Supersede`,
  `Release`, `Void`, `AttachSubject`.
- **`Snapshot/`** (immutable, minimal — a fat snapshot is a leaked query): one per command. E.g.
  `CommissionSnapshot` = the record's state + whether the desired alias is taken + whether the subject
  is already named. Nothing more.
- **`Decision/`** (immutable): a list of `Effect`s (`InsertRecord`, `UpdateState`, `AssignAlias`,
  `AttachSubject`, `SetSupersededBy`, `DeleteRecord`, `AppendAudit`) + a list of `Event`s. Effects are
  *descriptions* of writes; the core performs none and dispatches nothing.

**Fluent entry** `Sis::mint(IdClass::Client)->scopedTo(...)->...` returns a `Command` now, not a
register hit. `release_()` is removed: `Version::parse()` is pure core; minting a `REL` is the
`SupersedeIdentifier`/a dedicated `MintRelease` action in the shell.

**Testing (`Testing/`, shipped in the core):** `InMemoryProjection` (a read-only snapshot source) +
`DeciderConformanceSuite` — the shared suite any shell `Registrar` must pass. Proves the core drives
end to end with no database.

---

## §2.2 Application layer — actions, services, DTOs

The layering rule, stated once and enforced: **an Action is the only thing that builds a core
Command.** Controllers, console commands, jobs, seeders, and the facade all call Actions; none touch
the `Registrar` or the core directly. This is what stops the HTTP path and the CLI path from becoming
two subtly different "commission" implementations.

**Actions** (`Actions/`, invokable + a named method, stateless, constructor-injected, return a value
object): `MintIdentifier`, `ReserveIdentifier`, `CommissionIdentifier`, `CommissionWithAlias`,
`TransitionIdentifier`, `SuspendIdentifier`, `RestoreIdentifier`, `DecommissionIdentifier`,
`ReleaseIdentifier`, `SupersedeIdentifier`, `AttachSubject`, `DetachSubject` (reserved only),
`ResolveAlias`, `ResolveSubject`, `TraceSupersessionChain`, `DeriveAliasCandidates`,
`ValidateIdentifier`, `VerifyRegisterIntegrity`, `BackfillIdentifier`, `ReportCapacity`.

**Services** (`Services/`, interface-backed only where a second implementation is real):

| Service | Kind | Job |
|---|---|---|
| `SerialIssuer` | interface | sequence-backed atomic issuance (a consumer may want a different counter) |
| `AliasAllocator` | concrete | runs candidates → one `WHERE alias IN (…)` query → `choose` |
| `SnapshotBuilder` | concrete | one method per command; builds the **minimal** snapshot |
| `MorphResolver` | concrete | model ⇄ morph alias + the unknown-alias guard |
| `CapacityService` | concrete | fullness per class+scope |
| `IntegrityService` | concrete | recompute & compare check characters |
| `SupersessionService` | concrete | walk the chain, detect cycles, find the terminal successor |
| `IdempotencyService` | concrete | store/look-up/replay a decision, keyed `(actor, key)` |
| `WebhookDispatcher` | interface | a consumer may want their own transport |
| `Reporter` | interface | guarded, throttled (Part II rules 3, 8, 9) |

**DTOs** (`Data/`, `readonly`): the **constructor is the real validation gate** (protects HTTP + CLI +
queue + seeder, not just the HTTP path a FormRequest guards). Named constructors converge on it:
`CommissionData::fromRequest()/::fromArray()/::fromCommandLine()`.

Honest layering, written into `docs/laravel/architecture.md` and the PR template: an Action
orchestrates (no query, no rule); a Service does one impure job (no decision); the core decides (no I/O);
the Registrar applies (no decision). A rule that grows in an Action belongs in a core policy; a decision
that grows in a Service belongs in a decider.

**The Registrar decorator stack** (`Registrar/`), fixed order, outermost first, with a test asserting
the order and a test asserting no path reaches `EloquentRegistrar` without passing `AuthorizingRegistrar`:

```
Logging → OutboxRelaying → ConstraintTranslating → Transactional → Idempotent → Authorizing → Eloquent
```

Per call: load `Snapshot` → `decide(Command, Snapshot)` → apply effects + append audit + write outbox in
**one transaction** → DB constraints are the authority (translate violations to core exceptions) → relay
outbox after commit. The order is the documented default and is config-driven (a consumer may insert a
decorator), not a law.

---

## §2.3 Form requests and validation rules

**Form requests** (`Http/Requests/`), one per endpoint, each doing exactly three things:
`authorize()` → delegates to `IdentifierPolicy` (never an inline `if`); `rules()` → composed from the
custom rule objects (never a restated regex); `toData(): SomeData` → the controller never hand-builds a
DTO. Requests: `MintIdentifierRequest`, `ReserveIdentifierRequest`, `CommissionIdentifierRequest`,
`TransitionIdentifierRequest`, `SupersedeIdentifierRequest`, `AttachSubjectRequest`,
`ReleaseIdentifierRequest`, `ValidateIdentifierRequest`, `AliasCandidatesRequest`,
`CompareVersionsRequest`, `BackfillRequest`.

**Rules** (`Rules/`), each a thin delegate to the core — **none restates a rule**, every message names
the spec clause, every rule is translatable and usable **standalone** by a consumer:

`ValidIdentifier` (grammar + check), `ValidIdentifierOfClass`, `ValidCheckCharacters`, `AvailableAlias`,
`NotReservedAlias`, `ValidAliasShape`, `ValidLifecycleTransition`, `ValidSemver`, `KnownMorphAlias`,
`SubjectUnnamed`, `ValidSubtype`, `ScopeMatchesClass`.

Headline feature, documented prominently: `'invoice_ref' => ['required', new
ValidIdentifierOfClass(IdClass::Invoice)]` works in any consumer's own validation without the API. A
message reads e.g. "check characters do not verify (SIM-STD-0001:2026 §4) — a transposition was probably
introduced," not "invalid identifier."

---

## §2.4 Error handling, reporting, observability

Part II is normative. First-class deliverable: a design, a doc (`docs/errors.md`), a test suite, a
runbook.

**Classification.** Critical (unsafe/incorrect/insecure to continue → **throw in every environment**)
or degradable (safe reduced state → report via the central handler, record degraded state, continue).
Unclassified defaults to critical. **No environment branching anywhere.** For SIS almost everything is
critical (the audit's classification table stands): check mismatch, malformed id, illegal transition,
release of a commissioned id, space exhausted, alias/serial collision, unknown morph alias, immutability
trigger fired (a **security event**), unauthorised command, idempotency-key reuse with a different
payload — all critical. Degradable: outbox relay failure, cache miss on alias resolution, a notification
channel failing, a webhook endpoint down, a reap miss, read-model lag.

**Taxonomy** (`Contract\SisException` marker → consumers catch this):
`SisLogicException` (400), `SisIntegrityException` (500 + page), `SisStateException` (409),
`SisConflictException` (409), `SisCapacityException` (507); shell exceptions **extend** the core ones so
`catch(SisException)` still works (`UnauthorizedCommandException`, `UnknownMorphAliasException`,
`ImmutableWriteAttemptedException`, `IdempotencyConflictException` (422), `OutboxRelayException`,
`SisBootException`). Every exception: `context(): array` (operation, identifier, expected/actual,
decision, criticality, **actor by reference**, correlation_id, **spec_clause**, cause_type); preserves
`previous`; redacts secrets/PII (rule 15); cites its spec clause.

**Constraint-to-exception translation** — a first-class, fully-tested artefact, matched **on the
constraint name, never the message text**:

| SQLSTATE | Constraint | Throws |
|---|---|---|
| 23505 | `sis_alias_unique` | `AliasTakenException` |
| 23505 | `sis_serial_unique` | `SerialCollisionException` |
| 23505 | `sis_subject_unique` | `SubjectAlreadyNamedException` |
| 23514 | `identifier_shape`/`alias_shape` | `MalformedIdentifierException` |
| 23514 | `subtype_vocabulary` | `InvalidSubtypeException` |
| P0001 | `sis_immutability` | `ImmutableWriteAttemptedException` |
| P0001 | `sis_forbid_delete` | `CannotReleaseCommissionedException` |
| P0001 | `sis_audit_append_only` | `RegisterCorruptionException` |
| 40001 | serialisation failure | **retry**, bounded, with jitter — not translated |

**Reporting** — central handler (rule 3), **guarded** (rule 8, a broken Sentry never escalates a
degradable into a crash), **throttled** by operation identity (rule 9), one **correlation id** threaded
request → action → command → decision → audit → outbox → job → webhook, and **degraded state is
queryable** at `GET /api/sis/v1/health` (rule 7).

**Warnings** (rule 14, logged at warning to the same bar): alias fell through to a numeric discriminator;
a capacity threshold crossed; a retry succeeded on the second attempt; an alias-cache miss that should
have hit; outbox lag; a reservation about to lapse; a morph subject pointing at a vanished model.

**HTTP surface** — RFC 9457 `application/problem+json`, each exception → a **stable `type` URI**
anchored in `docs/errors.md` (**changing a `type` is a breaking change**); status from the taxonomy;
**no internal detail in a body** (a test asserts no rendered body contains a SQLSTATE, table name, file
path, or stack frame).

**`sis:doctor`** — the first thing anyone runs: schema present; **triggers installed and armed** (fire a
probe inside a rolled-back transaction and assert it raised — an installed-but-disabled trigger is a loud
failure); sequences ahead of max serial; morph map enforced and every stored alias resolvable; outbox
drained; no check-character failures in a sample; no orphaned subjects; capacity headroom; the audit
hash-chain intact; the boot report healthy. Non-zero exit otherwise.

---

## §2.5 Morphs — enforced, always

`sis_register` carries a polymorphic **subject** (`subject_type varchar(64)` = a **morph alias**, never
an FQCN; `subject_id varchar(64)` = string, taking int/uuid/ulid keys).

**`Relation::requireMorphMap()` is mandatory** — `SisMorphServiceProvider` boots **first** and its only
job is to enforce the map and **crash** if it is off while the registrar or API is enabled. A raw FQCN in
an immutable, never-deleted row is a time bomb (the day `App\Models\Client` moves, every historical row
points at a class that no longer exists — and the trigger will not let you fix them). The alias list is
governed like the class register: allocated once, never reassigned, retired with the thing it names, a
versioned published artefact (config + `docs/laravel/morphs.md` + the append-only `sis_morph_aliases`
table per decision D4). An unknown alias at write time is a **critical** `UnknownMorphAliasException`.

**Subject is frozen once commissioned** — `subject_type`/`subject_id` join the immutability trigger's
frozen list. **One thing, one identifier** — global `UNIQUE (subject_type, subject_id)` (decision D7).
**Nullable on purpose** — a RESERVED identifier has no subject yet (that is what reservation is for).

**Consumer trait `Concerns\HasSisIdentifier`** — relations + accessors **only**: `sisIdentifier()`
(`morphOne`), `sisId()` (the `Identifier` cast), `sisAlias()`, `whereSisAlias()`,
`withoutSisIdentifier()`. **It never boots a model event that mints on `created`** — minting is an
Action (authorized, audited, outboxed). This is stated in bold in `docs/laravel/models.md` and a test
asserts no register model observer mints.

Other morphs under the same map/rules: `sis_audit.actor` (polymorphic), `sis_webhook_endpoint.owner`
(optional). `superseded_by` is **not** polymorphic — it is identifier → identifier, a self-FK.

---

## §2.6 Data layer — migrations, factories, seeders

**Migrations** (publishable, ordered, reversible where safe, **explicitly irreversible** where not — an
append-only audit table's `down()` throws with a reason). Table names/connection configurable
(`sis.database.connection`, `sis.database.prefix`). Order: register → indexes (`sis_alias_unique`,
`sis_serial_unique`, `sis_subject_unique`, + state/class/expires/superseded_by) → serial sequences →
immutability triggers (frozen: identifier, class, scope, serial, spec_edition, alias, **subject_type,
subject_id**) → audit + append-only trigger → outbox → idempotency keys → `sis_morph_aliases` → webhook
endpoints → read-model tables. **Every trigger has a test that fires it, against real Postgres in CI**
(and MySQL per decision D6). `serialStart(STD)=1` and the `subtype_vocabulary` CHECK is reconciled with
`permitsSubtype()` (audit §7.5) so both layers agree.

**Factories** — **compute real check characters through the core** (never `fake()->regexify`); serials
from a test sequence; states mirror the machine and produce coherent rows (`reserved()` has
`reserved_at` and no `commissioned_at`; `void()` reachable only from reserved; etc.); `forSubject(Model)`
sets the morph **through the map** and fails if the model is unmapped. A test asserts every factory state
produces a row `Identifier::parse()` accepts and the triggers permit.

**Seeders** — `ReservedAliasSeeder` (idempotent upsert), `MorphAliasSeeder`, `DemoRegisterSeeder`
(dev/demo only; refuses a non-empty register and refuses in production — a guard on a destructive dev
tool, **not** the environment-branching Part II bans; the distinction is documented). **There is no
re-seed** — the triggers forbid truncation; `migrate:fresh` is the only reset, and it is a schema
operation. Seeders mint **through Actions**, not raw inserts.

---

## §2.7 Events and the outbox

The core **returns** events; the shell writes them to a **transactional outbox** in the same transaction
as the effects, then relays after commit. Without the outbox: dispatch-inside-txn emails a client about
an invoice that rolled back; dispatch-after-commit-in-process loses the event if the process dies between
commit and dispatch. Relay is **at-least-once** → **every listener must be idempotent** (stated in
`docs/laravel/events.md` in those words).

**Domain events** (core values, `Simtabi\SIS\Event\`): `IdentifierReserved`, `IdentifierCommissioned`,
`IdentifierTransitioned`, `IdentifierSuspended`, `IdentifierRestored`, `IdentifierDecommissioned`,
`IdentifierReleased`, `IdentifierVoided`, `IdentifierSuperseded`, `AliasAssigned`, `SubjectAttached`,
`ReservationLapsed`. **Shell events** (`Simtabi\Laranail\SIS\Events\`): `SerialSpaceNearingExhaustion`,
`AliasSpaceNearingExhaustion`, `RegisterIntegrityCheckFailed`, `ImmutableRecordWriteAttempted`,
`OutboxRelayLagging`, `ReadModelLagging`, `WebhookEndpointCircuitOpened`, `OrphanedSubjectDetected`.

Subscribers are **per-subscriber isolated** (one bad subscriber reports and is skipped). **Broadcasting
off by default**, opt-in per event.

---

## §2.8 Jobs, scheduling, notifications

**Jobs** (`Jobs/`) — each idempotent, explicit `$tries`/`$backoff`/`$timeout`/`uniqueId()`, configurable
queue/connection defaulting to the app's, **never `sync` by default, never assume Redis**, each calling an
Action: `RelayOutbox` (`ShouldBeUnique`), `ReapLapsedReservations` (→ VOID; never touches commissioned),
`VerifyRegisterIntegrity` (batched, resumable, read-only), `ReportSerialCapacity`, `BackfillIdentifiers`
(**dry-run by default**), `CompactSupersessionChains` (read model only), `DetectOrphanedSubjects`
(reports, **never deletes**), `DeliverWebhook` (signed, retried, circuit-broken), `PruneIdempotencyKeys`
(**the only thing in the package that deletes anything**). **Nothing deletes a register row** — no
soft-deletes, no `prunable`, tested.

**Scheduling** from `Providers\SisScheduleServiceProvider` (never asking a consumer to edit
`routes/console.php`), config-driven, every entry disableable: RelayOutbox every minute
(`withoutOverlapping(5)`, `onOneServer()`), ReapLapsedReservations hourly, ReportSerialCapacity daily
06:00, VerifyRegisterIntegrity weekly Sun 03:00 (`withoutOverlapping(240)`), DetectOrphanedSubjects
weekly, PruneIdempotencyKeys daily 03:30. **Boot fails loudly** if scheduling is on with a `file` lock
driver (`onOneServer()` needs redis/database/memcached).

**Notifications** (`Notifications/`) on the Part I §4 channel pattern, real Laravel notifications
(mail/Slack/database), **off by default** with an explicit recipient, **per-channel degradable** (a dead
Slack hook never suppresses the email): `SerialSpaceNearingExhaustion` ("PRJ scoped to ADIQ is 82%
through a 6-digit space — widen it"), `AliasSpaceNearingExhaustion`, `RegisterIntegrityFailure`,
`ImmutableWriteAttempted`, `ReservationLapsing`, `OutboxStalled`.

---

## §2.9 Audit

Append-only `sis_audit`: one row per applied `Effect` (identifier, action, **polymorphic actor**,
timestamp, before/after state, redacted originating command, idempotency key, correlation id, **plus the
ability checked and the resolver's verdict** — §2.10). **Append-only enforced by trigger** (no UPDATE, no
DELETE). Hash-chained (decision D5) so tampering under the trigger is detectable.
**`ImmutableRecordWriteAttempted` is a security event** — log at error, notify a human, record the
attempt; it means a bug in us or a person at a psql prompt. Redaction per rule 15 (refs and ids, never
secrets).

---

## §2.10 Authorization — gates, policies, pluggable RBAC

**The distinction that must not blur (top of `docs/laravel/authorization.md`): authorization is
orthogonal to legality.** The register decides *what is legal*; Laravel decides *who may ask*. A
superadmin cannot release a commissioned identifier — not for lack of a permission, but because the
operation does not exist in the decider. No role, wildcard, or `Gate::before` bypass reaches it.

**Abilities are a public contract** — a `SisAbility` string enum, governed like class codes (allocated
once, never reassigned; renaming silently revokes access in consumers' DB rows): `sis.register.view`,
`sis.audit.view`, `sis.identifier.reserve` (**gated harder** — reserving burns a serial permanently, so
an actor who can loop-reserve exhausts the space forever; it is the most dangerous ability, not the
safest), `.mint`, `.commission`, `.attach-subject`, `.suspend`, `.restore`, `.decommission`, `.supersede`,
`.release` (reserved-only, core-enforced), `sis.register.verify`, `.backfill`, `sis.webhooks.manage`.

**Granularity = ability × class × scope**, not a 308-permission matrix: the ability is the action, the
class and scope are arguments (`AuthorizationContext{class, scope, record}`). Class-level ("commission
invoices, not people") and **scope-level** ("commission for ADIQ, not ADLS") — the latter is the
multi-tenant control; without it any user who can raise an invoice can raise it against any client. A
worked scope-aware example ships in `docs/laravel/rbac.md`.

**Pluggable RBAC — support Spatie, depend on nobody.** `Gate` is the seam; resolution is pluggable behind
`Contract\PermissionResolver::allows(Actor, SisAbility, AuthorizationContext): bool`. Four
implementations: **`DenyAllResolver` (the default — ship denying)**, `GateResolver`,
`SpatiePermissionResolver` (`class_exists`-guarded; `suggest`), `ConfigRoleResolver`.
`config('sis.authorization.resolver')` picks one; a consumer binds their own by binding the interface.

**Policies & gates** — `Policies\IdentifierPolicy` (model-bound abilities) registered **explicitly** in
`SisAuthServiceProvider` (not auto-discovered — an implicit security control is one you cannot grep for);
gates for the model-less abilities; both funnel into the configured resolver (**one decision point**).
**`Gate::before` is a loaded gun** — documented: return `null` for `sis.*` (fall through), never blanket
`true`; and the ceiling holds (even a total bypass cannot make an illegal operation legal).

**Actors** — `Actor` (core value, a reference, never a name/email); `ActorResolver` (shell) maps
`Authenticatable` → `Actor` and produces the non-human actors (scheduler, console operator, API client,
job). **Every command has an actor**; a guest denies every stateful ability; the stateless endpoints need
no actor. The actor + the ability checked + the verdict are recorded on **every audit row**.

**Enforcement** — `AuthorizingRegistrar` checks the ability **before the decider runs**, so an
unauthorised command never opens a transaction and never burns a serial. **Every write path goes through
it** (tested). Ships: the enum, `AuthorizationContext`, the resolver + four impls, `IdentifierPolicy`,
gates, `SisAuthServiceProvider`, `SisPermissionSeeder` (idempotent, + role presets sis-viewer/operator/
registrar/admin as a *starting point*), `sis:permissions` ("who can do what, and why the 403"),
`Sis::actingAs()` + a `Testing\FakeAuthorizer`.

---

## §2.13 Security and the threat model (placed before the API, per the prompt)

**Security first shapes the design, not a section at the end.**

**The thing everyone gets wrong: an identifier is a name, not a capability.** ISO 7064 detects a typo, not
an attacker — anyone can compute a valid `SIM-INV-ADIQ-000042-XX`. If a consumer treats "they knew the
reference" as authorisation, that system is broken. Stated in the README, `docs/errors.md`, and
`docs/security.md`. Unforgeable identifiers would need a keyed MAC and a different spec — out of scope.

**Threat model** (`docs/security.md`, reviewed when the API surface changes):

| Threat | Mitigation |
|---|---|
| **Permanent DoS by serial exhaustion** (the nastiest — serials never reused, no cleanup) | authorize `reserve` separately and above; **per-actor quota**, not just a rate limit; aggressive capacity alerting; expiry-void (reclaims nothing, so quotas are the real control). Headlined. |
| **Idempotency-key cross-tenant replay** | key scoped to **`(actor, key)`**, never `key` alone |
| **SSRF via webhook URLs** | `Security\UrlGuard`: block RFC 1918 / loopback / link-local / **169.254.169.254**; resolve DNS and validate the **resolved IP** (defeats rebinding); **no redirects**; timeout; allowlist mode |
| **IDOR on reverse lookup** (`GET /subjects/{type}/{id}`) | authorize as hard as a write; **404 for both "not found" and "not yours"** |
| **Enumeration** | global serials from 100001; scoped from 1 (spec-accepted leak, documented); scope list endpoints to the actor; rate-limit reads |
| **Trigger bypass** | the app DB role is **not superuser and does not own the table**; `sis:doctor` **probes triggers are armed** |
| **Audit tampering** | append-only + **hash-chain** (decision D5) → detectable |
| **Timing** | `hash_equals` in check verify and webhook signing |
| **Secret exposure** | webhook secrets encrypted at rest, **write-only** over the API (never returned, regenerate instead), never logged / in a body / in `context()` |
| **Mass assignment** | `$guarded = []` forbidden; register models have **no mass-assignable attributes** (every write goes through the Registrar) |
| **Injection / ReDoS / log injection** | bindings only; validate shape through core value objects first; bound input length before regexes; strip CR/LF from logged user values |
| **Queue payload leakage** | pass identifiers/ids, never serialized models |
| **Supply chain** | `composer audit` in CI, Dependabot, signed tags, the core's zero-dep `composer.json` |
| **Privilege escalation via `Gate::before`** / **cross-tenant write via missing scope** / **permission-string drift** | §2.10 mitigations |

**Safety** — every destructive/bulk op is **dry-run by default** + explicit `--force` (`sis:backfill`,
`sis:reap`); **no `--force` deletes a register row** (no code path does — Part II rule 5: no runtime switch
downgrades an invariant); confirm in production on bulk writes; the health endpoint leaks nothing to an
unauthenticated caller; `sis:doctor` output is operator-only, never over HTTP.

**Process** — `SECURITY.md` (real disclosure address, SLA, supported-versions, "security fixes ship as
patch releases to every supported line"); CI gates: `composer audit`, secret scanning, dependency review,
**Psalm taint analysis on the shell** alongside PHPStan (justified: taint into query builders and the
outbound HTTP client is a real risk given the SSRF surface — the core runs PHPStan only).

---

## §2.11 The HTTP API

Shell-side, **opt-in** (`config('sis.api.enabled')` default `false`; prefix/middleware/rate-limit
configurable). **The primary surface** (§2.12), same care as the core. Thin controllers (FormRequest in,
Action call, Resource out — a controller with a rule is a bug).

**Stateless** (pure core, no register): `POST /validate`, `POST /check-characters`,
`GET /alias-candidates`, `POST /versions/compare`, `GET /classes`, `GET /morph-aliases`.

**Stateful** (Action → Registrar): `POST /identifiers` (reserve|commission), `GET /identifiers` (from the
**read model**), `GET /identifiers/{id}`, `POST /{id}/commission`, `POST /{id}/transition`,
`POST /{id}/subject`, `POST /{id}/supersede`, `GET /{id}/chain`, `GET /{id}/audit`, `DELETE /{id}`
(release; 409 unless RESERVED), `GET /aliases/{alias}`, `GET /subjects/{type}/{id}` (IDOR-hardened),
`GET /health`.

Non-negotiables: **`POST /identifiers` requires an `Idempotency-Key`** (stored with the request hash +
resulting decision; replay returns the same identifier; a key reused with a different payload is 422) —
the idempotency middleware is built **before any write endpoint exists**; errors per §2.4 (409 for state
and conflict); reads hit the **read model** (`Read/`), never the Registrar (CQRS-lite); versioned API
Resources; **OpenAPI 3.1** in `docs/` + a **contract test** the routes match it; webhooks HMAC-signed,
timestamped, replay-windowed, queued, retried, per-endpoint circuit-broken; auth is the consumer's
(default `auth:sanctum` if present, **deny otherwise**).

---

## §2.12 Headless — no frontend, ever

No Blade, asset, stylesheet, JS, `resources/js`, or `package.json`. The surface is **JSON over HTTP** +
**Artisan**. Both go through the same Actions, so there is one "commission" implementation and a **parity
test** asserts every Action has both an HTTP and a console entry point (or a documented reason).
**OpenAPI 3.1 is first-class** (how every non-PHP consumer gets a client + the contract test).
**Stable, versioned JSON** (`/v1/`, problem+json `type` URIs are a public contract). Auth pluggable,
stateless by default (no session/CSRF/cookie reliance), CORS is the consumer's (docs show a working
config). **Panel adapters (Filament/Nova) are consumers** — optional, `class_exists`-guarded, in their own
providers, calling Actions; delete them and nothing else notices. **No frontend dependency in any
`composer.json`.** `docs/laravel/headless.md` shows SPA / mobile / another service / queue worker.

---

## §2.14 Open-source production readiness

- **Works out of the box:** `composer require` → `php artisan sis:install` (publish config + migrations,
  scaffold the morph map, migrate, run `sis:doctor`). **Zero required config to start; everything
  configurable.**
- **Everything configurable, nothing hard-coded to Simtabi:** connection, prefix, morph/reserved aliases,
  serial widths/starts, capacity thresholds, cache store/TTL, queue names/connections, every schedule
  cadence + enable flag, notification recipients/channels, webhook settings, idempotency window, API
  prefix/middleware/rate-limit, **the alias strategy class**, and **the registrar decorator stack**. The
  `SIM` issuer prefix is **config** — someone else must be able to run this standard for their own company
  by changing one value.
- **BC promise:** `BACKWARD-COMPATIBILITY.md` naming public API (facade, Actions, Rules, value objects,
  events, problem+json `type` URIs) vs not (decider internals, decorator concrete classes);
  `roave/backward-compatibility-check` in CI.
- **Supported-versions table** (PHP, Laravel, Postgres, MySQL) with EOL dates; repo furniture (LICENSE,
  CoC, CONTRIBUTING, SECURITY, templates, CODEOWNERS, keep-a-changelog, Dependabot, `.gitattributes`
  export-ignore, funding, badges); `composer validate --strict`; **both split packages installable
  standalone**, tested in CI from the split.
- **Release:** tag the monorepo → GitHub Action subtree-splits to two read-only repos → Packagist webhooks
  (noting the laranail VCS-url convention: inter-package deps resolve via VCS, not Packagist).

---

## §2.15 Extension points

**Restraint governs.** SIS is a specification implementation; most of it **must not** be extensible — the
check algorithm, grammar, class register, and state machine **are** the guarantee. Those are `final`, no
interface. **Extensible, and this is the complete list:** the `Registrar` + decorator stack
(config-driven), the read model, `AliasStrategy`, `SerialIssuer`, event subscribers, notification
channels/routing, webhook transport/endpoints, **the `PermissionResolver`**, the `ActorResolver`.

**The legality seam does not exist:** a consumer may replace how a permission is *resolved*; they may not
replace what is *legal*. No resolver/role/bypass makes releasing a commissioned identifier possible.

Panel adapters (Part III): a Filament plugin and a Nova tool, each in its own provider, `class_exists`
-guarded, thin over the Actions. Ships `Simtabi\SIS\Testing\` (`InMemoryProjection` +
`DeciderConformanceSuite`) in the core and `Sis::fake()` in the shell.

---

## §2.16 Standards and tooling

- **PHP `^8.5`, Laravel `^13.0`, no shims.** PSR-1/4/12 (PER-CS 2.0). PSR-3/14 **shell only** (the core
  has no logger/dispatcher). `declare(strict_types=1)` everywhere; `final` by default; asymmetric
  visibility on value objects/DTOs; `#[\NoDiscard]` on `decide()`/`Identifier::mint()`/`SerialIssuer::next()`;
  `clone with` on the `Minter` and immutable commands.
- **Deptrac, gating CI.** Layers `Core/Value ← Core/Policy ← Core/Decider ← Laravel/Service ←
  Laravel/Action ← Laravel/Http`; the core may not reference `Illuminate\`, `Simtabi\Laranail\`, PDO,
  `DateTime*` construction, or a global; a controller may not reference a `Registrar`.
- **Toolchain (Part I §12):** Pint, PHPStan **level 10 core / 9 + Larastan shell, no baseline**, Rector
  (php83 set to keep syntax portable? — **no**, this is 8.5-only, so Rector targets php85), Peck,
  php-parallel-lint, Lefthook, the GitHub Actions layout. **Psalm taint on the shell** in addition
  (justified above). **Infection** on `CheckCharacters`, `LifecycleState`, `Version`, the deciders, high
  MSI. **Property-based tests** on the check characters (the four claims). CI: core on **PHP 8.5, no
  framework installed**; shell on **Laravel 13** via the matching testbench; a **real Postgres (and
  MySQL) job firing every trigger**, including a test that a **disabled** trigger is caught by
  `sis:doctor`; a job installing the core split alone that runs `composer why illuminate/support` and
  expects a **non-zero** exit. Security gates blocking: `composer audit`, secret scanning, dependency
  review, `composer validate --strict`, BC check.

---

## §2.17 Migration and versioning

- **What breaks for anyone on the current code:** `RegistryInterface` disappears. Before: `new
  Sis($registry)` orchestrating writes through a port. After: `Sis::mint(...)` builds a `Command`; an
  Action + the `Registrar` apply it. A before/after ships in `docs/upgrading.md`.
- **Backfill:** `sis:backfill` imports an existing estate, reports collisions, attaches morph subjects,
  handles the grandfathered pre-SIS rows (decision D3), **dry-run by default**.
- **Spec edition** `SIS/1` stamped on every record; when `SIS/2` exists the register carries both, old
  identifiers keep their edition, nothing is rewritten. **Package version ≠ spec edition.**
- **The morph alias list is versioned like the class register** — adding an alias is a minor release;
  reassigning one is forbidden.
- **SemVer:** a change altering a check character is a **new spec edition and a new major**, not a minor;
  **changing a problem+json `type` URI is breaking.**

---

## Summary and next step

The design is functional-core / imperative-shell with an Action→Service→Registrar→core spine, DB
constraints as the authority and core preconditions advisory, morphs enforced, deny-by-default pluggable
RBAC orthogonal to legality, a transactional outbox, a first-class error taxonomy with problem+json, a
threat model that shapes the API, and a headless JSON+Artisan surface — all PHP 8.5 / Laravel 13, split
from one monorepo into `simtabi/sis` (zero-dependency core) + `laranail/sis-wrapper`.

All nine deferred decisions are resolved (section D). The two governance items — extracting the spec to a
normative repo file and amending §11's reference-implementation name — are flagged for the maintainer and
assumed by the design.

**This phase stops here for review.** Phase 3 (build) begins only on approval, in small reviewable
commits following the prompt's build order: Deptrac + CI + security gates first; then the core (values →
exceptions → CheckCharacters + golden/property tests → Identifier/IdClass/LifecycleState/Version →
policies → commands/snapshots/deciders + conformance suite → fluent entry); then the shell (morph map
first → migrations + trigger tests → models → services → DTOs → Actions → EloquentRegistrar + decorator
stack → error layer → outbox/events → factories/seeders → jobs/scheduling/notifications → authorization →
read model/Rules/console → security layer → idempotency middleware + API + parity test → webhooks →
panels); then docs, OSS furniture, split workflow, tags.
