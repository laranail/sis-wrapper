# SIS — Phase 1: Repo audit

`SIM-STD-0001:2026` reference implementation → `simtabi/sis` (core) + `laranail/sis-wrapper` (shell).

This is the Phase 1 deliverable required by the design prompt: a complete read of the existing repo
against the specification, the operating bible, the engineering reference, and the `laranail/*`
house packages. No package code is written in this phase. Every bug cites a real `file:line`; every
contradiction is surfaced with options, cost, and a recommendation (the prompt forbids silently
picking a winner). Work stops after this document for review, before Phase 2 (design).

**Sources read for this audit:** all 12 repo files directly; the `laranail/*` package sources
(`package/tools`, `tools/console`, `enumerator`); the spec text itself (embedded in
`simtabi-operating-bible.md` lines 977–1698, read and verified — not taken from a summary); the
operating bible; and the three-part engineering reference.

---

## 0. The load-bearing finding: the pure core cannot express the headline invariants

The prompt says this finding, if it exists, goes first. It exists, and it is the reason the obvious
reading of "core = logic, Laravel = data" produces a broken package.

SIS's headline guarantees are **stateful**. *A commissioned identifier is never released, reused, or
reissued* (§6.3.2); serials unique within class and scope (§9); aliases unique across the company,
forever (§5). None of these can be decided by a function that cannot see the register. Sort each
concern by whether a pure function can answer it:

**Decidable by a pure function over values (belongs in the core):**

- the grammar and both forms (§2)
- the check characters (§4)
- whether a lifecycle transition is *legal* as a rule (§6.2)
- the ranking of alias candidates for a legal name (§5.2)
- semver precedence between two releases (§7.2)
- *advisory* preconditions — "given this snapshot of the register, does this alias look free, is this
  transition allowed from this state" — computed from data handed in, never fetched

**Not decidable without the register (belongs in the shell, with the database as the authority):**

- *has this identifier ever been issued* / *is this serial the next one* / *is this alias taken
  anywhere, ever* — inherently stateful
- atomic serial issuance (§9: "two callers MUST NOT receive the same serial")
- storage-layer locking of commissioned rows (§6.4: "a trigger, not a good intention")

The consequence, and the spine of the whole design: **core preconditions are advisory; database
constraints are authoritative.** "Is this alias free?" is a check-then-act, and check-then-act loses
races — two requests can both see `ADIQ` as free. What holds the line is the unique partial index and
the immutability trigger. The core precondition exists to produce a fast, precise, well-worded failure
*before* any I/O; the DB constraint exists to be correct under concurrency; the shell catches the
violation and rethrows *the same* core exception. That is defence in depth, not a DRY violation, and it
must be documented as such so a later reader does not "clean it up."

This reframes the existing `RegistryInterface` design (the core orchestrating writes through a port) as
the prompt's "Wrong A": it drags transactions, atomic serial issuance, and uniqueness into a package
that claims no persistence. The correction is functional-core / imperative-shell (the decider pattern):
`decide(Command, Snapshot): Decision`, pure, returning descriptions of writes the shell applies inside
one transaction.

Nothing in the specification is inexpressible under this split — every invariant lands cleanly on one
side or the other. That is the single most important result of this audit.

---

## 1. Bible conformance

The operating bible is normative on how Simtabi builds and ships, and it outranks the design prompt.
It is mostly a business-operations manual plus the formal standard, and it **delegates** repo README /
`docs/` / CHANGELOG authoring to `~/.claude/CLAUDE.md`, and CI / testing / security bars to the
engineering reference. The rules that touch this work, and current conformance:

| Bible rule | Requirement | Current repo |
|---|---|---|
| Package README spine | Exactly 4 plain-shields badges: registry version · Tests · Static analysis · License, in that order | ✗ no README |
| Headings | Sentence-case; command/class names in backticks | ✗ n/a yet |
| No decorative emoji | Semantic glyphs `← · → ✓` only; no `✅/🔶` | ✓ (nothing to violate) |
| Voice | Itch → Proof → Invite; craftsperson-to-peer; ≤8-word benefit taglines; banned-words list (seamless, empower, leverage, robust, "whether you're X or Y", triadic adjective stacks, …) | ✗ n/a yet |
| Licence | MIT, `Copyright (c) <year> Simtabi LLC` | ✗ no LICENSE |
| `.github` health files | SECURITY, CODE_OF_CONDUCT, CONTRIBUTING, issue/PR templates cascade | ✗ absent |
| Brand layer | laranail = Crimson `#D7263D`; package-repo badges stay plain (brand hex only on brand surfaces) | ✗ n/a yet |
| Identity values (exact) | `Simtabi LLC`; `opensource@simtabi.com` / `imani@simtabi.com`; canonical `opensource.simtabi.com/{products,documentation}/{org}/{product}` URLs | ✗ no composer.json / metadata yet |

**Divergences between the prompt and the bible:** none material. The prompt's README/badge/voice/docs
rules restate the bible and the global standard; the prompt's PHPStan "level 10 core / 9 shell, no
baseline" is consistent with the engineering reference's "level 10 for new code" (this is greenfield).
Where the two ever conflict in later phases, the bible wins and the divergence is logged here.

**Precedence reminder for later phases:** (1) the spec is normative on identifier behaviour; (2) the
bible on how we build; (3) the engineering reference Part II on how code fails; (4) the `laranail/*`
sources on their own APIs; (5) this prompt yields to all of the above.

---

## 2. Laranail API surface (read from source)

The adopted-API surface, read from the actual sources (not READMEs), with the version constraints the
shell will require.

### `package-tools` — `laranail/package-tools`

- **Path** `laranail/package/tools`; **namespace** `Simtabi\Laranail\Package\Tools\`; **PHP**
  `^8.4.1 || ^8.5`; **Illuminate** `^13.0`; depends on `laranail/console: ^0.1`.
- **Provider base** `Providers\PackageServiceProvider` (extends `Illuminate\Support\ServiceProvider`).
  Real lifecycle hooks, in order: `registeringPackage()` → `configurePackage(Package $package)`
  (the one abstract method) → `packageRegistered()` → (boot) `bootingPackage()` → `packageBooted()`.
- **`Package` DSL** covers, confirmed present: `hasConfigFile()` + namespaced/nested/global-merge
  config, `hasMigration()/discoversMigrations()`, factories & seeders, `hasCommand()/hasConsoleCommand()`,
  `hasInstallCommand(InstallCommandDefinition|callable)`, `registerScheduledCommand()/schedulesUsing()`,
  `hasTranslations()`, `hasViews()`, `hasRoute()/hasRoutesWhen()/registerRouteGroup()`, `hasAssets()`,
  Blade components, morph maps, observers, policies, middleware, events, lifecycle closure hooks. This
  covers every seam the prompt asks of `configurePackage()`.
- **`InstallCommandDefinition`** (fluent): `named()`, `publishes(...tags)`, `runsMigrations()`,
  `runsSeeders()`, `asksToRunMigrations()`, `copiesServiceProvider()`, `asksToStarRepo()`,
  `step(label, Closure)`. Backs `sis:install`.
- **`CronBuilder`** (scheduling lives here, not in console): `daily/weekly/monthly`, `at()`,
  `everyMinutes()`, `withoutOverlapping`-style via the schedule integration, `toExpression()`.
- **Namespaced names** `Commands\Command` + `Commands\Concerns\SupportsNamespacedNames` (writes
  Symfony's private `name`/`aliases` past `validateName()` so `laranail::sis.<command>` is legal).
- **What it does NOT give us:** the register's domain — decider stack, snapshot building, constraint
  translation, outbox, morph enforcement, idempotency. All bespoke to this package.

### `console` — `laranail/console`

- **Path** `laranail/tools/console`; **namespace** `Simtabi\Laranail\Console\`; **PHP** `^8.4.1 || ^8.5`;
  **Illuminate** `^13.0`; deps `laravel/prompts`, `symfony/console ^8.0`.
- **Base command** `Simtabi\Laranail\Console\Tools\Commands\Command` (thin; standard Laravel
  `$signature`/`handle()` + `argument()`/`option()`, plus `InteractsWithConsoleServices` and
  `InteractsWithConsoleWriter`). Every `sis:*` command extends this.
- **Service layer** `CommandServiceManager` → `interaction()` (`askText/askConfirm/askSelect/
  askWithValidation/showSpinner`), `display()`, `logger()`, `error()`, `performance()`. **Output**
  `ConsoleWriter` (`success/error/warning/info/note/line`). **`Prompter`** fluent forms + ~30 validators.
- **Namespaced names** its own canonical `Tools\Commands\Concerns\SupportsNamespacedNames` (the
  package-tools copy is a duplicate of this).
- **Floor consequence** depending on `console` for the command base raises the shell PHP floor to
  `^8.4.1`. Immaterial — the target is PHP 8.5 only.
- **What it does NOT give us:** scheduling helpers (those are in package-tools), and any domain logic.

### `enumerator` — `laranail/enumerator`

- **Path** `laranail/enumerator`; **namespace** `Simtabi\Laranail\Enumerator\`; **PHP** `^8.3`;
  **Illuminate** `^13.0`.
- **What it adds over a native enum:** attribute-driven metadata (`Label/Description/Color/Icon/Order/
  Meta`), `IsTranslatable` labels/i18n, `AsEnum`/`AsNullableEnum`/`AsEnumeratorCollection`/`AsBitmask`
  casts, five `ValidationRule`s (`EnumValue/EnumName/EnumIn/EnumNotIn/EnumTransition`), query scopes,
  on-enum and Eloquent-enforced state machines with a history table, bitmasks, `CasesCollection`,
  route binding, class-based dynamic/DB-backed "enums," and Filament/Livewire/Nova/Inertia/GraphQL/
  OpenAPI/MCP emitters. Cost: reflection + a multi-layer cache and a large surface.
- **Verdict.** `IdClass` and `LifecycleState` are native enums in the core and **stay that way** (the
  prompt is explicit). The only question is whether the shell's *decoration* of them needs enumerator.
  **Leaning drop for the core enums:** a native enum + a small Eloquent cast + our own
  core-delegating `Rules/` (`ValidLifecycleTransition`, `ValidSubtype`, …) already covers labels,
  casting, and validation, and each of our rules must cite a spec clause in its message — a bespoke
  message enumerator does not give us. enumerator earns its place only if the shell needs its i18n
  label system or bitmask/state-history machinery, which SIS does not (audit and supersession are
  bespoke). **Final one-sentence verdict is a Phase 2 deliverable**; the prompt allows either call
  with a justification, and this is the justification for dropping it.

### Baseline

All three packages support Laravel 13; PHP 8.5 satisfies every floor. **The `orchestra/testbench`
major that pairs with Laravel 13 is deliberately not asserted here — it is verified against primary
sources in Phase 2 / build-step-0, per the prompt's "do not write a call against a signature you have
not read."**

### Flag

`laranail/package/tools/composer.json` requires `laranail/console: ^0.1`, but the laranail org
`CLAUDE.md` states package-tools requires `console ^1.0`. Per the fixed inter-package convention (VCS
urls, a single moving `v0.1.0` tag, constraints stay `^0.1`) the **`^0.1` in composer is correct** and
the org doc is stale. Surfaced for a human to correct the doc; not worked around here.

---

## 3. Inventory + disposition

The repo is **not a git repository** and contains exactly 12 flat files: 11 PHP + 1 SQL. There is **no
`composer.json`, no autoloader, no tests, no README, no `docs/`, no config, no CI**. Files declare
directory namespaces (`Simtabi\Sis`, `…\Registry`, `…\Support`, `…\Exception`) but sit flat, and
`example.php` requires a non-existent `vendor/autoload.php` — **the code as laid out cannot autoload or
run.**

| File | Defines | Disposition | Destination / note |
|---|---|---|---|
| `IdClass.php` | `enum IdClass: string` (22 cases) | **refactor** | core `Identifier/`; fix `serialStart()` (STD) + `permitsSubtype()` (empty-vocab); re-case namespace |
| `LifecycleState.php` | `enum LifecycleState: string` | **keep** (pure) | core `Identifier/`; clean state machine, `canTransitionTo()` already pure |
| `CheckCharacters.php` | `final class CheckCharacters` | **keep** (pure) | core `Support/`; add golden-vector + property tests it claims but lacks |
| `Identifier.php` | `final readonly class Identifier` | **keep + extend** | core `Identifier/`; wrap with `Scope`/`Serial`/`Alias` value objects |
| `Mnemonic.php` | `final class Mnemonic` | **refactor → `AliasPolicy`** | core `Policy/`; split `candidates()` (pure ranking) from a shell query + `choose()`; ext-intl caveat (§5) |
| `Version.php` | `final readonly class Version` | **fix** | core `Version/`; replace `strcmp` prerelease compare with semver §11; decide product-mismatch |
| `Sis.php` | `final class Sis`, `final class Minter` | **rebuild** | core fluent entry now returns a `Command`; `release_()` removed/relocated; `Minter` no longer touches a registry |
| `Record.php` | `final class Record` (mutable) | **replace** | immutable `Snapshot` (core) + `SisRecord` Eloquent model (shell) |
| `RegistryInterface.php` | `interface RegistryInterface` | **delete** | replaced by the decider + shell `Registrar`; state leaves the core |
| `InMemoryRegistry.php` | `final class InMemoryRegistry` | **delete → `Testing/InMemoryProjection`** | its enforcement moves to DB constraints; a read-only projection survives for tests |
| `register.sql` | Postgres DDL + 2 triggers | **rebuild** | shell migrations; add audit + append-only trigger, outbox, idempotency, morph subject; portable-vs-PG decision (§8) |
| `example.php` | usage script | **delete** | replaced by docs + tests |

Two classes worth keeping largely intact today — `LifecycleState` and `CheckCharacters` — are exactly
the two that are already pure. That is not a coincidence; it is the audit's finding (§0) showing up in
the file list.

---

## 4. The domain and its load-bearing invariants

Verified against the spec text (§ references are to `SIM-STD-0001:2026`). For each: the invariant, and
what breaks if it breaks.

- **Grammar (§2).** Two forms only. Form G `SIM-{CLASS}-{SERIAL}-{CHECK}`; Form S
  `SIM-{CLASS}-{SCOPE}-{SERIAL}-{CHECK}`. Normative regexes: G `^SIM-[A-Z]{3}-[0-9]{6,9}-[0-9A-Z]{2}$`,
  S `^SIM-[A-Z]{3}-[A-Z][A-Z0-9]{3,5}-[0-9]{6,9}-[0-9A-Z]{2}$`. Serial 6–9 digits. Comparison is
  case-insensitive and ignores separators (§2.4). *Load-bearing.* The prototype's `FORM_G`/`FORM_S`
  regexes match the spec exactly.
- **Class register (§3), 22 classes.** Codes never reassigned; a retired code retires with it. Scoped
  (Form S, serial from 1): PRJ, SOW, CHG, MIL, QUO, INV, CRN, DOC, TKT, ENV. Global (Form G, serial
  from 100001): CLT, PRS, VND, DPT, PRD, SVC, CMP, REL, AST, ADR, INC — **and STD, which the spec
  starts at 000001 (see §8.2)**. Aliased classes (§5): CLT, PRD, SVC, CMP, DPT. Reserved *class codes*
  (§3.6): SIM, STD (except as defined), TST, TMP, XXX, NIL, VOI, SYS, ADM, ROO — all enforced by
  construction, since `IdClass` is a closed enum containing none of them but the STD carve-out. *The
  prototype's `isScoped()` and `usesAlias()` match the spec; `serialStart()` does not (STD).*
- **Check characters (§4).** ISO 7064 MOD 1271-36, two characters, over base-36. Detects 100% of
  single-substitution, adjacent-transposition, jump-transposition, and twin errors — MOD 37,36 and MOD
  97-10 were measured and rejected (§4.2; do not re-propose). Every identifier MUST carry a valid check;
  a bad check is rejected, never repaired (§4.3). *Load-bearing:* an invoice reference that survives a
  digit swap is a payment that cannot be reconciled. The prototype implements the algorithm and uses
  `hash_equals` in `verify()` — but has **no test proving the four claims** (§7.8).
- **Lifecycle (§6).** `RESERVED → {COMMISSIONED, VOID}`; `COMMISSIONED → {SUSPENDED, DECOMMISSIONED}`;
  `SUSPENDED → {COMMISSIONED, DECOMMISSIONED}`; DECOMMISSIONED and VOID terminal. Invariants (§6.3): no
  path returns to RESERVED; **a commissioned identifier is never released, reused, or reissued**; a
  commissioned identifier never becomes VOID; terminal is terminal. Locking (§6.4) MUST be enforced at
  the storage layer. *The single most important rule in the spec.* The prototype's `LifecycleState`
  encodes the transitions correctly and is already pure.
- **Aliases (§5).** Grammar `[A-Z][A-Z0-9]{3,5}` (4–6 chars), globally unique, immutable once
  commissioned (it is the SCOPE segment of every Form S id for that client — the DNS pattern).
  Derivation *widens before it mangles* (§5.2): 4 chars, then 5, then 6, then a numeric discriminator.
  Reserved aliases (§5.3): SIMT PROS TEST NULL VOID TEMP DEMO NONE ADMIN ROOT SYST. *The prototype's
  `RESERVED_ALIASES` matches §5.3 exactly.*
- **Supersession (§8).** Identifiers and their records are never edited; a correction is a new
  identifier plus a `superseded_by` pointer. The chain is the audit trail. *The prototype writes the
  pointer but has no chain traversal or cycle detection (§7.9).*
- **Register (§9).** One table; frozen columns (identifier, class, scope, serial, alias, spec_edition)
  vs mutable (state per §6.2 only, description, owner→PRS, subtype). Serial issuance MUST be atomic;
  immutability MUST be in the database. *The prototype's `register.sql` enforces the frozen columns and
  the no-delete rule with two triggers — but is Postgres-only and has no audit trail (§7.10).*

### Golden vectors the spec hands us (for the tests the prototype lacks)

- **Check-character vectors (Annex A):** `SIM-CLT-100001-9O`, `SIM-PRS-100001-FA`, `SIM-PRD-100001-H3`,
  `SIM-AST-100001-8W`, `SIM-SOW-ADIQ-000001-NZ`, `SIM-INV-ADIQ-000001-VY`. These become the fixture the
  `CheckCharacters` docblock promises and lacks.
- **Alias-ranking vectors (§5.2):** AdelsaIQ LLC→`ADIQ`, Acme Corp→`ACME`, Acme Inc→`ACMX`, Acme
  Industries Ltd→`AICM`, Café Solutions GmbH→`CAFE`, Zed & Partners LLP→`ZEDX`, Northwind Traders→`NORS`,
  Northwind Technologies→`NOND`, Simtabi LLC→`SIBI`. These are **authoritative expected outputs**; the
  prototype `Mnemonic` must be tested against them, and the non-obvious ones (AICM, NORS, NOND) may
  expose ranking bugs to fix in Phase 3.

---

## 5. Purity audit

Every impurity in the current code and where it goes:

| Impurity | Location | Resolution |
|---|---|---|
| `new DateTimeImmutable()` (reserve) | `InMemoryRegistry.php:72` | time becomes a command input (`occurredAt`); the shell supplies it |
| `new DateTimeImmutable()` (commission) | `InMemoryRegistry.php:105` | same |
| `new DateTimeImmutable()` (transition ×2) | `InMemoryRegistry.php:145,149` | same |
| `new DateTimeImmutable()` default (`hasLapsed`) | `Record.php:48` | `now` passed in; the sweep is a shell job |
| `nextSerial()` read-modify-write | `InMemoryRegistry.php:49` | shell `SerialIssuer` over a DB sequence (also §7.3) |
| `aliasAvailable()` lookups | `Sis.php:250,262,278` | shell `AliasAllocator`: core ranks (`candidates`), shell queries, core picks (`choose`) |
| Postgres-only DDL | `register.sql` | shell migrations; portable-vs-PG decision (§8) |

**Zero-dependency-core caveat.** `Mnemonic::transliterate()` (`Mnemonic.php:115`) uses `ext-intl`
(`transliterator_transliterate`) with an `ext-iconv` fallback and a raw-string last resort. The core
`composer.json` is to be "php ^8.5 and nothing else." Resolution options for Phase 2: declare
`ext-intl`/`ext-iconv` as PHP-extension `require`s (extensions are not Composer packages, so the core
stays dependency-free in the packaging sense), or keep the graceful fallback and document that
transliteration degrades to raw ASCII when neither extension is present. This is the only place the
"zero-dependency core" claim needs a footnote.

---

## 6. Hidden state

- **`Minter`** clones on every fluent call (`Sis.php:152,161,170`) — correct; a half-built minter cannot
  leak state into another call. Keep the pattern (it becomes `clone with` under PHP 8.5).
- **`Record`** is **mutable and mutated in place after being handed out by reference.**
  `InMemoryRegistry` returns a `Record`, then later assigns `$record->state`, `$record->alias`,
  `$record->commissionedAt`, etc. (`InMemoryRegistry.php:101-109,142-150,163`). Anyone holding an earlier
  reference sees it change under them. Decision (per the architecture): `Record` becomes an **immutable
  `Snapshot`** in the core — the minimal facts a decision needs — and the mutable row is the shell's
  `SisRecord` Eloquent model, written only through the `Registrar`. One thing, one responsibility.

---

## 7. Bugs and gaps (confirmed by direct read)

Each is fixed in Phase 3; the fix approach is noted.

1. **`Version::compare()` uses `strcmp()` on the pre-release string** — `Version.php:93`. Not semver
   2.0.0 §11 precedence: `rc.10` sorts *below* `rc.2`, and the numeric-identifier-vs-alphanumeric rule
   is never applied. It **also ignores `product` entirely**, so it silently orders two different
   products' versions as if they were the same product. *Fix:* dot-split identifier comparison
   (numeric compared numerically, numeric ranks below alphanumeric, more fields wins); decide
   product-mismatch behaviour (throw, or document that `compare` assumes same product).
2. **`Sis::release_()` never touches the register** — `Sis.php:110-113`. It parses a string to a
   `Version` and returns it; it mints no `REL` identifier, allocates no serial, writes nothing. The
   trailing underscore is the API admitting the method is misplaced. *Fix:* version parsing is a pure
   core function; minting a `REL`-class identifier is an Action. Two separate things.
3. **`nextSerial()` is non-atomic and ignores pinned serials** — `InMemoryRegistry.php:49-55`. The
   interface says it MUST be atomic; this is a plain array read-then-write. Separately, a `withSerial()`
   pin (`Sis.php:170`) does not advance the counter, so a later `nextSerial()` can return a value that
   collides with a pin. *Fix:* shell `SerialIssuer` over a DB sequence, gap-tolerant, reuse-intolerant;
   the counter is the sequence, and pins are validated against uniqueness at the DB.
4. **Alias reservation is not transactional with commissioning** — `Sis.php:242-244`. `commissionAs`
   derives/checks the alias, then calls `reserve()` and `commission()` as two separate writes with no
   transaction; two callers can both pass the check and both proceed. *Fix:* the unique partial index on
   `alias` is the authority; the shell translates the violation back into `AliasTakenException`.
5. **`IdClass::permitsSubtype()` disagrees with the SQL `subtype_vocabulary` CHECK.** PHP —
   `IdClass.php:108` — returns `true` for *any* subtype when the class has an empty vocabulary
   (`$vocabulary === [] || in_array(...)`). The SQL CHECK (`register.sql:63-69`) has **no branch** for a
   class outside {AST, DOC, PRS, DPT}, so a non-null subtype on e.g. `CLT` or `INV` **fails the database
   constraint while passing the PHP check** — two enforcement layers give opposite answers. *Fix:* empty
   vocabulary ⇒ subtype must be `null` (`$vocabulary === [] ? $subtype === null : in_array(...)`).
6. **`IdClass::serialStart()` is wrong for `STD`** — `IdClass.php:69-72`. It derives the start from
   `isScoped()` (`scoped ? 1 : 100001`); STD is global, so it returns 100001. But §3.4 starts STD at
   **000001**. `serialStart()` needs a per-class value, not a boolean derivation. Tied to the STD
   contradiction (§8.2).
7. **The exception taxonomy is referenced everywhere and defined nowhere.**
   `Simtabi\Sis\Exception\{InvalidIdentifierException, ImmutableIdentifierException,
   ExhaustedSpaceException}` have **zero class definitions** in the repo, yet are thrown across
   `Sis.php`, `Identifier.php`, `InMemoryRegistry.php`, `CheckCharacters.php`. **Every throw path fatals
   with "class not found."** Their static factories name the API the new taxonomy must provide:
   `::scopeMismatch, ::missing, ::malformed, ::badCheck, ::illegalCharacter, ::aliasTaken,
   ::reservedAlias` (Invalid); `::locked, ::illegalTransition, ::cannotRelease` (Immutable); `::serial,
   ::alias` (Exhausted). *Fix:* build the full taxonomy from §2.4.2 of the prompt, each with `context()`
   and a cited spec clause.
8. **No check-character golden vectors / property tests.** `CheckCharacters.php:16-23` claims exhaustive
   verification (100% substitution / adjacent transposition / jump transposition / twin error) with
   nothing backing it. *Fix:* the Annex A vectors (§4 above) plus a property-based test asserting no
   single-character substitution, adjacent/jump transposition, or twin error yields a valid identifier —
   or delete the claim. An unbacked claim in a comment is worse than no comment.
9. **No supersession-chain traversal, no expiry sweep, no capacity warning.** `supersede()` writes a
   pointer (`InMemoryRegistry.php:163`) but nothing walks the chain, detects cycles, or resolves an
   identifier to its terminal successor. `Record::hasLapsed()` exists (`Record.php:42`) but nothing
   calls it. `ExhaustedSpaceException` fires only when the space is *already gone* — nobody is warned at
   80%. *Fix:* shell `SupersessionService`, a reap job, and a capacity service emitting warnings.
10. **`register.sql` is Postgres-only, has no audit trail, and has no subject link.** The two triggers
    (`sis_immutability`, `sis_no_delete`) are the entire §6.4 guarantee and have **no tests**. There is
    no `sis_audit` table / append-only trigger, and no way to answer "which model is `SIM-CLT-100001`?"
    or "what is this Client's identifier?" (the morph gap). *Fix:* rebuilt migrations with audit +
    append-only trigger, outbox, idempotency, and an enforced polymorphic subject; every trigger gets a
    test that fires it against real Postgres.
11. **Nothing runs.** No `composer.json`, no autoloader, flat files under directory namespaces,
    `example.php` requires a missing `vendor/autoload.php`.
12. **Width/length agreement unverified.** Form S scope is 4–6 (`FORM_S`, `Identifier.php:26`),
    `Sis::MAX_ALIAS_LENGTH` is 6 (`Sis.php:40`), SQL is `varchar(6)` with an `[A-Z][A-Z0-9]{3,5}` shape
    (`register.sql:20,51`). These agree by inspection; *confirm by test, not by eye.*
13. **Minor.** Duplicate edition constant — `Sis::EDITION` (`Sis.php:38`) and `Identifier::EDITION`
    (`Identifier.php:23`) both `'SIS/1'`, with `Record::specEdition` defaulting to `Identifier::EDITION`
    (`Record.php:32`); collapse to one source of truth (a `SpecEdition` value). The SQL `owner` FK
    (`register.sql:27`) is unconstrained where §9 says `owner → PRS`. `CheckCharacters::for()`'s
    `illegalCharacter` throw (`CheckCharacters.php:39`) is effectively dead — `normalise()` strips
    everything but `[A-Z0-9]`, all of which are in the alphabet.

---

## 8. Contradictions (options + cost + recommendation — none silently resolved)

### 8.1 The spec is not a file in the repo, and §11 names a different package

`SIM-STD-0001:2026` lives *inside* `simtabi-operating-bible.md` (lines 977–1698). The prompt calls it
"the specification in the repo" — it is not. Separately, §11 states "the reference implementation is the
`simtabi/identifier` package," while the settled target for the core is `simtabi/sis`.

- **Option A** — extract the spec verbatim to a normative `SIM-STD-0001-2026.md` at the repo root, and,
  following the spec's own §8/§10 supersession discipline, publish an amendment updating §11 from
  `simtabi/identifier` to `simtabi/sis`. *Cost:* one governance edit; the code then has a single
  normative source it can cite by section.
- **Option B** — leave the spec embedded in the bible and cross-reference it. *Cost:* the code's
  normative source lives inside an operations manual, invites drift, and cannot be shipped in the
  package's `docs/`.
- **Recommendation: A.** It is also what the spec's own permanence rules imply.

### 8.2 `STD` is internally inconsistent in the spec itself

The spec contradicts itself on STD, and the prototype ignores all versions of it:

- §3.4 (line 1155): STD is **Form G, serial starts 000001, "4-digit serial permitted."**
- Annex A (line 1602): the specimen is `SIM-STD-000001` — a **6-digit** serial.
- Grammar §2.3: `serial = 6*9DIGIT` — 6–9 digits only; a 4-digit serial is ungrammatical.
- The standard's own designation `SIM-STD-0001:2026` is a **4-digit, check-less** label.
- Prototype: STD → `serialStart()` returns **100001** (global default), 6–9 digits.

- **Option A** — register STD identifiers are ordinary Form G with a **6-digit serial starting at
  000001** (matches Annex A and the grammar); fix `serialStart(STD) = 1`. Treat `SIM-STD-0001:2026` as
  an **ISO-style standard number**, not a SIS identifier (it has no check characters and predates its
  own grammar), and document that distinction. *Cost:* a documentation note + a one-line `serialStart`
  special-case; honours §2.3 and Annex A.
- **Option B** — widen the grammar to permit 4-digit STD serials. *Cost:* narrows the serial minimum,
  which §10 forbids for issued identifiers; also breaks the uniform regex. **Reject.**
- **Option C** — raise a spec amendment to resolve §3.4 vs Annex A. *Cost:* governance latency; pairs
  well with A.
- **Recommendation: A (with C to tidy the spec text).**

### 8.3 A grandfathered identifier that the grammar rejects

Annex C.2 keeps `SIM-2607-ADIQ-001` valid forever (a pre-SIS invoice already delivered to AdelsaIQ),
but its class position is `2607` (four digits, not three letters) and its serial is 3 digits — it
matches **neither** Form G nor Form S, so `Identifier::parse()` throws on it (`Identifier.php:73`). §10
forbids ever invalidating a prior-edition identifier.

- **Option A** — the register tolerates pre-SIS rows behind a `spec_edition` marker (e.g. `'pre-SIS'`)
  that bypasses grammar/check validation on read; such rows are never minted anew, only backfilled.
  *Cost:* a narrow validation escape hatch — but a real one, needed by any consumer with a legacy
  estate. Wire it into `sis:backfill` (§2.17).
- **Option B** — refuse to store it. *Cost:* violates §10; makes the package unable to represent
  Simtabi's own history.
- **Recommendation: A.**

### 8.4 Namespace casing

Prototype is `Simtabi\Sis`; the targets are `Simtabi\SIS\` (core) and `Simtabi\Laranail\SIS\` (shell).
Settled by the prompt's fixed-facts — recorded as a rename to apply in Phase 3, not an open question.

---

## 9. Decisions deferred to Phase 2 (flagged now so nothing is lost)

The prompt's explicit "flag it as a decision" items, plus the new ones this audit surfaced. None are
resolved in Phase 1; each carries into the design with a recommendation.

- **Morph map storage** — a `sis_morph_aliases` append-only table vs a config array. (Table = auditable,
  cannot be quietly edited; config = simpler.)
- **Audit hash-chaining** — each `sis_audit` row carries a hash of its content plus the previous row's
  hash, making tampering *detectable* under the append-only trigger. (Costs write throughput; buys
  tamper-evidence.)
- **Postgres-only vs portable per-driver DDL** — the triggers are the whole guarantee; never ship a
  weaker guarantee under the same name. (Recommend Postgres-first, loudly documented, with the door open
  to per-driver DDL that preserves the guarantee.)
- **Unique subject globally vs per-class** — `UNIQUE (subject_type, subject_id)` ("one thing, one
  identifier," the thesis) vs unique-per-class (one thing carries identifiers in several classes).
- **STD serial resolution** (§8.2), **grandfathered-legacy handling** (§8.3), **`ext-intl` in a
  zero-dependency core** (§5), the **`permitsSubtype` fix** (§7.5), the **`enumerator` keep/drop
  verdict** (§2), and the **spec-extraction + reference-impl rename** (§8.1).

---

## 10. Summary and next step

The prototype is a faithful but incomplete sketch: the two genuinely pure classes (`LifecycleState`,
`CheckCharacters`) are close to production-ready, the identifier grammar and class register are correct,
and the spec is well-understood. Everything stateful is either wrong for the target architecture
(`RegistryInterface`, `InMemoryRegistry`, mutable `Record`, non-atomic `nextSerial`) or missing (the
exception taxonomy, the audit trail, the morph subject, the tests, the whole application/HTTP/security
surface). The central architectural result (§0) is that this is expected and correct: the invariants
partition cleanly into a pure core (advisory) and an imperative shell (authoritative), and no spec
invariant is left inexpressible.

**Highest-priority items** carried into Phase 2/3, in order: (1) the functional-core / imperative-shell
split with advisory-precondition + DB-authority (§0); (2) resolving STD (§8.2) and the spec's home
(§8.1) before any class-register code is written; (3) the exception taxonomy (§7.7) and the
`permitsSubtype`/`serialStart` fixes (§7.5–7.6) that make the core self-consistent with its own storage
layer; (4) the check-character property test (§7.8) using the Annex A vectors.

**This phase stops here for review.** Phase 2 (the design document) begins only on approval, and will
open with bible conformance and the laranail API surface, then resolve the deferred decisions (§9) with
recommendations.
