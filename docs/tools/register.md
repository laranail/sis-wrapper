# The class register and lifecycle

The profile-driven class register and the five-state lifecycle machine (`LifecycleState`) — what an identifier *is* and what may happen to it. The register is **configuration**: it is built from `config('sis.classes')` into a `SisProfile`, so a company defines its own vocabulary. The table below is the reference SIM register the package ships. For the SDK's authoritative treatment, see the SDK's [register](https://opensource.simtabi.com/documentation/simtabi/sis-sdk/register) and [profiles](https://opensource.simtabi.com/documentation/simtabi/sis-sdk/profiles) docs.

## Identifier grammar

An identifier takes exactly one of two forms (§2):

```
Form G — global:   SIM-{CLASS}-{SERIAL}-{CHECK}          SIM-PRS-100001-FA
Form S — scoped:   SIM-{CLASS}-{SCOPE}-{SERIAL}-{CHECK}  SIM-INV-ADIQ-000001-VY
```

| Segment | Rule | Mutable? |
|---------|------|----------|
| `SIM` | Issuer prefix (configurable). | Never. |
| `CLASS` | Exactly 3 uppercase letters; a member of the register below. | Never, once commissioned. |
| `SCOPE` | `[A-Z][A-Z0-9]{3,5}` (4–6 chars); the owning client's alias. Form S only. | Never. |
| `SERIAL` | 6–9 digits, zero-padded. | Never. |
| `CHECK` | 2 characters, derived (see [check characters](check-characters.md)). | Never — a function of the rest. |

Every segment of a commissioned identifier is immutable; comparison is case-insensitive and ignores separators (`sim-prs-100001-fa` = `SIMPRS100001FA` = `SIM-PRS-100001-FA`).

## The default 22-class register

The shipped register carries 22 classes, defined as rows in `config('sis.classes')` and resolved into `ClassDefinition` value objects on the `SisProfile`. Edit, add, or remove rows to run the standard for your own organisation (see [configuration → class register](../configuration.md#class-register)). Global (Form G) serials start at `100001` so the sequence never advertises how many things exist; scoped (Form S) serials start at `1`. `STD` is the deliberate exception: a global class that starts at `1`. The `SimClass` enum in the SDK is a convenience handle on the reference codes (`SimClass::CLIENT` = `'CLT'`).

| Code | Class | Form | Serial start | Alias? | Subtypes |
|------|-------|:----:|:------------:|:------:|----------|
| `CLT` | Client | G | 100001 | ✓ | — |
| `PRS` | Person | G | 100001 | — | `ENG` `DES` `PM` `OPS` `BIZ` `EXE` |
| `VND` | Vendor | G | 100001 | — | — |
| `DPT` | Department | G | 100001 | ✓ | `ENG` `DES` `OPS` `BIZ` `FIN` `EXE` |
| `PRJ` | Project | S | 1 | — | — |
| `SOW` | Statement of Work | S | 1 | — | — |
| `CHG` | Change order | S | 1 | — | — |
| `MIL` | Milestone | S | 1 | — | — |
| `QUO` | Quote | S | 1 | — | — |
| `INV` | Invoice | S | 1 | — | — |
| `CRN` | Credit note | S | 1 | — | — |
| `PRD` | Product | G | 100001 | ✓ | — |
| `SVC` | Service | G | 100001 | ✓ | — |
| `CMP` | Component | G | 100001 | ✓ | — |
| `REL` | Release | G | 100001 | — | — |
| `AST` | Asset | G | 100001 | — | `LAP` `MON` `PHN` `SRV` `LIC` `DOM` `KEY` `MSC` |
| `DOC` | Document | S | 1 | — | `ICA` `MSA` `SOW` `NDA` `CHG` `DPA` `IPA` `EMP` `QUO` |
| `STD` | Standard | G | 1 | — | — |
| `ADR` | Decision record | G | 100001 | — | — |
| `TKT` | Ticket | S | 1 | — | — |
| `INC` | Incident | G | 100001 | — | — |
| `ENV` | Environment | S | 1 | — | `TST` `DEV` `SPT` `TRN` `STG` `PRD` |

Each `ClassDefinition` exposes `isScoped()`, `serialStart()`, `usesAlias()`, `subtypes()`, `permitsSubtype()`, and `label()`. The `GET /classes` endpoint projects the configured register for discovery, so it reflects a custom profile automatically.

### Subtypes are attributes, not segments

A subtype (§3.7) is a value in the register's `subtype` column, **never** a segment of the identifier — a laptop's identifier is `SIM-AST-100001-8W`; *that it is a laptop* (`LAP`) is a column. A class with an empty `subtypes` list carries no subtype; its column must be null. The register enforces the vocabulary with a `subtype_vocabulary` `CHECK` constraint generated from the profile, so it mirrors `ClassDefinition::permitsSubtype()` exactly for whatever classes you configure.

## The lifecycle state machine

`LifecycleState` encodes the single most important rule in the specification: **a commissioned identifier is never released, reused, or reissued** (§6). The machine makes it structurally impossible — no transition leads back to `Reserved`.

```
                ┌──────────────┐
   reserve ───▶ │   RESERVED   │ ───▶ VOID   (terminal — issued in error, never used)
                └──────┬───────┘
                       │ commission (locks forever)
                       ▼
              ┌────────────────┐   suspend   ┌────────────┐
              │  COMMISSIONED  │ ◀─────────▶ │ SUSPENDED  │
              └────────┬───────┘   restore   └─────┬──────┘
                       │ decommission              │ decommission
                       ▼                           ▼
                ┌──────────────────┐  (terminal — the thing is gone; the identifier is not)
                │  DECOMMISSIONED  │
                └──────────────────┘
```

| State | Meaning | Releasable? | Locked? | Terminal? |
|-------|---------|:-----------:|:-------:|:---------:|
| `Reserved` | Allocated, not yet in use. | ✓ | — | — |
| `Commissioned` | In use. Immutable forever. | — | ✓ | — |
| `Suspended` | Temporarily inactive; still owned. | — | ✓ | — |
| `Decommissioned` | Retired; identifier persists. | — | ✓ | ✓ |
| `Void` | Reserved then never used. | — | ✓ | ✓ |

`allowedTransitions()`, `canTransitionTo()`, `isReleasable()`, `isLocked()`, and `isTerminal()` answer the machine's questions. Invariants (§6.3): no path returns to `Reserved`; a commissioned identifier never becomes `Void` (an error in a commissioned record is corrected by [supersession](../recipes/supersede-an-identifier.md), not voiding); `Decommissioned` and `Void` are terminal.

## Correction is supersession, never editing

Identifiers and the records that carry them are never edited. A wrong invoice is credited (`CRN`) and reissued under a new `INV`; a wrong document gets a new version with `superseded_by` set. The chain of supersession is the audit trail.

---

[← Docs index](../../README.md#documentation)
