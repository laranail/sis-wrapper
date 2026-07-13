# The `Sis` facade

`Sis` (`Simtabi\Laranail\SIS\Facades\Sis`) is the programmatic register API — the full register without going through HTTP. It resolves `SisManager`, so every stateful call runs the same `Action → registrar-decorator stack` the controllers use: authorization, transactions, audit, and the outbox all apply identically.

## Stateful register operations

Each runs through the registrar stack. `$actor` is optional — it defaults to the current authenticated actor (`ActorResolver::current()`).

| Method | Signature | Does |
|--------|-----------|------|
| `reserve` | `reserve(IdClass $class, ?string $scope = null, string $reason = '', ?Actor $actor = null, int $width = 6): Identifier` | Reserve a serial (§6.5). |
| `commission` | `commission(Identifier $id, ?Alias $alias = null, string $description = '', ?SubjectRef $subject = null, ?Actor $actor = null): Identifier` | Lock a reserved identifier; optionally bind alias + subject. |
| `suspend` | `suspend(Identifier $id, ?Actor $actor = null): Identifier` | Commissioned → suspended. |
| `restore` | `restore(Identifier $id, ?Actor $actor = null): Identifier` | Suspended → commissioned. |
| `decommission` | `decommission(Identifier $id, ?Actor $actor = null): Identifier` | Retire (terminal). |
| `transitionTo` | `transitionTo(Identifier $id, LifecycleState $state, ?Actor $actor = null): Identifier` | Any legal transition (§6.2). |
| `supersede` | `supersede(Identifier $id, Identifier $successor, ?Actor $actor = null): Identifier` | Record supersession (§8); returns the successor. |
| `attachSubject` | `attachSubject(Identifier $id, SubjectRef $subject, ?Actor $actor = null): Identifier` | Bind the thing a reserved identifier names. |

```php
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Alias;

$id = Sis::reserve(IdClass::Product, reason: 'new SKU');
Sis::commission($id, alias: Alias::of('MALISA'), description: 'Malisa platform');
Sis::suspend($id);
Sis::restore($id);
```

## Reads

Delegate to the read model — they do not run through the registrar stack.

| Method | Returns |
|--------|---------|
| `find(Identifier $id)` | `SisRecord\|null` |
| `resolveAlias(string $alias)` | `Identifier\|null` — the canonical identifier for a mnemonic |
| `resolveSubject(SubjectRef $subject)` | `Identifier\|null` — reverse lookup |
| `chain(Identifier $id)` | `list<Identifier>` — the supersession chain, terminal successor last |
| `terminalSuccessor(Identifier $id)` | `Identifier` — the end of the chain |

## Pure passthroughs to the core

These call straight into the zero-dependency `simtabi/sis` core — no register access.

| Method | Returns |
|--------|---------|
| `mint(IdClass $class)` | `Minter` — the fluent command builder |
| `isValid(string $value)` | `bool` |
| `parse(string $value)` | `Identifier` |
| `classOf(string $value)` | `IdClass\|null` |
| `aliasCandidates(string $legalName)` | `AliasCandidates` |
| `version(string $value)` | `Version` — a parsed release version (§7.2) |

```php
Sis::isValid('SIM-INV-ADIQ-000001-VY');          // true
Sis::classOf('SIM-PRS-100001-FA');                // IdClass::Person
Sis::aliasCandidates('AdelsaIQ LLC')->all();      // ['ADIQ', 'ADEL', ...]
Sis::version('MALISA-2.0.0-rc.1')->precedes(Sis::version('MALISA-2.0.0'));   // true
```

## `SisManager`

The facade is a thin front for `Simtabi\Laranail\SIS\Services\SisManager`, which you can inject directly. It composes the actions (`ReserveIdentifier`, `CommissionIdentifier`, `TransitionIdentifier`, `SupersedeIdentifier`, `AttachSubject`, …), the `ActorResolver`, and the `SisReadModel`. The stateful methods build the command envelope (actor, timestamp, a fresh correlation id and idempotency key) and dispatch through the same stack as HTTP — so a facade call and a POST are indistinguishable to the register.

---

[← Docs index](../../README.md#documentation)
