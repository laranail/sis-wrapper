# Architecture

How the system is built: a pure functional core wrapped in an imperative Laravel shell, with the same rules enforced twice — once in code, once in the database.

## Two packages, one boundary

| Package | Namespace | Role |
|---------|-----------|------|
| `simtabi/sis` | `Simtabi\SIS\` | The **functional core** — total functions over immutable values. Grammar, check characters, the class register, the lifecycle state machine, alias derivation, deciders. Zero dependencies. |
| `laranail/sis-wrapper` | `Simtabi\Laranail\SIS\` | The **imperative shell** — persistence, transactions, authorization, HTTP, the outbox, webhooks, scheduling. Everything with a side effect. |

The boundary is enforced by `deptrac`: the core must not reference the shell. The core never persists, reads a clock, logs, or dispatches — it builds commands and answers questions, and the shell applies the commands it produces.

## Functional core, imperative shell

The core exposes a *decision* layer: given a command and a minimal snapshot of the register, a pure `Decider` returns a `Decision` — a list of effects to apply and domain events to emit — or throws a domain exception. It touches no database.

```
Command  +  Snapshot   ──Decider.decide()──▶   Decision (effects + events)
(what to do) (minimal      pure, total            what the shell must apply
             read state)   no I/O
```

`Decider::decide()` pairs each command with its decider and snapshot type (`Reserve`/`ReserveSnapshot`, `Commission`/`CommissionSnapshot`, and so on). The shell's registrar loads the snapshot, calls the decider, and applies the returned decision.

## Actions: the only thing that builds a command

An **Action** is the single implementation each register operation shares across HTTP, the facade, the console, and queued jobs. A controller is thin — a `FormRequest` in, an Action call, a `Resource` out; a controller with a rule in it is a bug. The Action:

1. authorizes the ability **before** issuing a serial (so an unauthorised actor never burns one);
2. wraps the operation in idempotency keyed on the request payload (a retry replays, it does not act twice);
3. builds the core command and hands it to the registrar.

The real validation gate is the DTO constructor (`ReserveData`, `CommissionData`, …), which validates through the core policies — so a Laravel rule never restates a rule the core owns, and the HTTP path, the CLI path, the queue, and a seeder are all protected identically.

## The registrar decorator stack

The registrar applies a command to the register. It is a stack of decorators, assembled from config by `RegistrarFactory` (outermost first):

```
LoggingRegistrar               structured log + correlation id; logs and rethrows, never swallows
  └ OutboxRelayingRegistrar    after commit, eagerly relays the outbox; relay failure is degradable
      └ ConstraintTranslatingRegistrar   catches DB constraint/trigger violations, rethrows the
          │                              SAME core exception the advisory check would have raised
          └ TransactionalRegistrar       one transaction: effects + audit + outbox + idempotency
              └ AuthorizingRegistrar     re-checks the full command (defence in depth)
                  └ EloquentRegistrar     innermost: load snapshot → run decider → apply effects → write outbox
```

Ordering is deliberate:

- `ConstraintTranslating` sits **outside** the transaction, so it catches violations surfaced at *commit*, not just at statement time.
- `OutboxRelaying` sits **outside** the transaction too, so it only relays events that actually committed.
- `Authorizing` sits **inside**, as defence in depth: a test asserts no path reaches `EloquentRegistrar` without passing it, even though the Action already pre-authorized.

## Advisory core, authoritative database

The core's preconditions are **advisory**; the database is **authoritative**. Both enforce the same invariants:

| Invariant | Core (advisory) | Database (authoritative) |
|-----------|-----------------|--------------------------|
| Identifier grammar (§2) | `Identifier::parse()` regex | `identifier_shape` `CHECK` |
| Check characters (§4) | `CheckCharacters::verify()` | (verified on read; corruption caught by `sis:doctor`) |
| Lifecycle transitions (§6.2) | `LifecycleState::canTransitionTo()` | immutability triggers |
| Commissioned = locked (§6.4) | decider refuses | trigger rejects any edit to a frozen row |
| Subtype vocabulary (§3.7) | `IdClass::permitsSubtype()` | `subtype_vocabulary` `CHECK` |
| Alias / serial / subject shape | value objects | `alias_shape`, `serial_positive`, `subject_pair` `CHECK` |

The register table carries `CHECK` constraints for every frozen shape and immutability triggers on the trigger-capable drivers (PostgreSQL, MySQL 8). So a bad row cannot exist even if application code has a bug — the `ConstraintTranslatingRegistrar` then re-surfaces a lost race as the *same* exception type whether it failed at the check or at the commit. A grandfathered pre-SIS row (Annex C.3) bypasses the shape checks via its `spec_edition`.

## Enforced morph map

`SisMorphServiceProvider` boots first and calls `Relation::enforceMorphMap()`. From that point, an unmapped morph is a loud Eloquent failure, never a silently stored class name. The subject an identifier names crosses every boundary as a morph **alias** (`SubjectRef` = alias + id), never a fully-qualified class name — because a raw FQCN in an immutable, never-deleted row breaks the day you rename a namespace. The register is designed to outlive your namespace layout.

## Transactional outbox

Domain events are written to the `sis_outbox` table in the *same transaction* as the effects, then relayed after commit — eagerly by the `OutboxRelayingRegistrar`, and as a safety net by the scheduled `RelayOutbox` job. Relay is at-least-once, so every listener must be idempotent. This is what lets a webhook or a downstream projection never miss an event and never see an event for a write that rolled back.

## Audit trail

Every command writes an append-only audit row (identifier, action, actor reference, before/after state, ability, verdict, correlation id). The table is append-only *by database trigger*, and hash-chaining (`hash` / `prev_hash`) makes tampering *under* the trigger detectable. The supersession chain plus the audit trail is the history; editing history destroys it, so identifiers and records are never edited — a correction is a new record with `superseded_by` set (§8).

## Why …?

**Why a pure core at all?** The four guarantees that must never be wrong — the grammar, the ISO 7064 check, the class register, and the state machine — are total functions with no I/O. Isolating them makes them exhaustively testable (the check algorithm is proven by property-based tests over every identifier shape) and reusable outside Laravel. `IdClass` and `LifecycleState` are native enums *by design*: they must not be extensible, because an extensible class register is a class register that can be corrupted.

**Why enforce rules in the database when the code already checks them?** Application code has bugs, and a register is forever. The spec (§6.4, §9) mandates storage-layer enforcement precisely because "we'll be careful in the service layer" is how immutable data gets mutated. The code check gives a good error message and avoids a round-trip; the database check is the guarantee.

**Why a decorator stack instead of one service?** Each concern — logging, outbox relay, constraint translation, transactions, authorization, persistence — is independent and independently orderable. The order carries meaning (constraint translation outside the transaction, authorization inside as defence in depth), and a consumer can insert their own decorator without forking the package. Rationale lives here, not in a separate ADR tree.

**Why does authorization ship denying?** A package that ships open ships a breach. `DenyAllResolver` forces an explicit opt-in. Crucially, authorization is orthogonal to legality: it decides *who may ask*, never *what is legal* — no resolver can authorize an illegal operation, because the decider rejects it regardless of the asker.

---

[← Docs index](../README.md#documentation)
