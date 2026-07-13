# Authorization

Deny-by-default, pluggable RBAC: fourteen abilities, a swappable `PermissionResolver` seam, and class/scope-aware checks — all orthogonal to legality.

## Ships denying

The default resolver is `DenyAllResolver`, which denies every ability. A package that ships open ships a breach, so a consumer must explicitly opt in by configuring a resolver or binding their own.

```php
'authorization' => [
    'resolver' => DenyAllResolver::class,   // change this
    'resolvers' => [
        'deny-all'     => DenyAllResolver::class,
        'gate'         => GateResolver::class,            // delegates to Laravel's Gate
        'spatie'       => SpatiePermissionResolver::class, // spatie/laravel-permission (suggest)
        'config-roles' => ConfigRoleResolver::class,       // a role => [abilities] map in config
    ],
],
```

## Authorization is orthogonal to legality

This is the load-bearing rule: a resolver decides **who may ask**, never **what is legal**. No resolver, role, or bypass can make an illegal operation legal — the pure decider rejects an illegal command regardless of who is asking. Granting `sis.*` to an actor does not let them commission an identifier twice or return a commissioned one to `RESERVED`; those are refused by the state machine, not the authorizer.

## The ability list

`SisAbility` is a **public contract** — these strings appear in consumers' permission tables and role definitions, so they are governed like class codes: allocated once, never reassigned, retired with the thing they name. Renaming one silently revokes access in every app that stored it.

| Ability | Value |
|---------|-------|
| View register | `sis.register.view` |
| View audit | `sis.audit.view` |
| Reserve | `sis.identifier.reserve` |
| Mint | `sis.identifier.mint` |
| Commission | `sis.identifier.commission` |
| Attach subject | `sis.identifier.attach-subject` |
| Suspend | `sis.identifier.suspend` |
| Restore | `sis.identifier.restore` |
| Decommission | `sis.identifier.decommission` |
| Supersede | `sis.identifier.supersede` |
| Release | `sis.identifier.release` |
| Verify integrity | `sis.register.verify` |
| Backfill | `sis.register.backfill` |
| Manage webhooks | `sis.webhooks.manage` |

> `Reserve` is gated **harder** than the rest and should be granted to fewer actors than `Commission`: reserving burns a serial permanently, and serials are never reused, so an actor who can reserve in a loop can exhaust the space forever. It is the most dangerous ability in the package, not the safest.

## Class and scope granularity

`AuthorizationContext` carries the `IdClass`, the `scope`, and (when known) the record — enough granularity without a permission matrix. The ability is the action; the class and scope are arguments:

- **Class-level** — "commission invoices, not people".
- **Scope-level** — "commission for `ADIQ`, not `ADLS`". This is the multi-tenant control: without it, any user who can raise an invoice can raise it against any client in the company.

The `SpatiePermissionResolver` understands scope-aware permission strings: it checks `sis.identifier.commission.adiq` first, then falls back to the bare `sis.identifier.commission`.

## Where the check happens — twice

`Authorizer` maps a command to its ability and context, then asks the resolver. It runs at two points (defence in depth):

1. **In the Action, before a serial is issued** — so an unauthorised actor never burns one (`authorizeAbility()`).
2. **In the `AuthorizingRegistrar`, on the full command** — a test asserts no path reaches persistence without it.

A denied check raises `UnauthorizedCommandException`, rendered as `403`.

## The resolver seam

```php
namespace Simtabi\Laranail\SIS\Contract;

interface PermissionResolver
{
    public function allows(Actor $actor, SisAbility $ability, AuthorizationContext $context): bool;
}
```

Bind any implementation — Spatie, Bouncer, a homegrown roles table, an IdP's claims, or nothing. Laravel's Gate is the seam (`GateResolver` delegates straight to it), and this contract is the escape hatch. Every shipped resolver **fails closed**: an unknown or mistyped permission is a denial, never a silent allow (`SpatiePermissionResolver` catches a Spatie exception and denies; `ConfigRoleResolver` with no role resolver bound denies; absent Spatie it denies). See [plug in Spatie permissions](../recipes/plug-in-spatie-permissions.md).

## Actors

`ActorResolver` maps an authenticated model to an `Actor` (a PII-free morph reference like `user:9`, never a name or email) and produces the non-human actors: `system()` (the scheduler), `console()`, and `guest()` (denies every stateful ability). Every command has an actor — "the system did it" is an answer; "nobody knows" is not.

## Inspecting permissions

```bash
php artisan sis:permissions                      # the ability list + current resolver
php artisan sis:permissions --actor=user:1       # exactly what user:1 may do
```

This is the first thing to run when "it says 403 and I don't know why". See [the Artisan commands](console.md).

---

[← Docs index](../../README.md#documentation)
