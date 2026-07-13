# Plug in Spatie permissions

Back SIS authorization with `spatie/laravel-permission` instead of the deny-all default.

The package ships denying. Point the resolver at `SpatiePermissionResolver`, which maps each `SisAbility` to a Spatie permission and checks it — understanding scope-aware permission strings. Spatie is a `suggest`, never a `require`; absent it, the resolver denies.

```bash
composer require spatie/laravel-permission
```

```php
// config/sis.php
'authorization' => [
    'resolver' => \Simtabi\Laranail\SIS\Authorization\SpatiePermissionResolver::class,
],
```

Grant the ability strings (the [ability list](../tools/authorization.md#the-ability-list)) as Spatie permissions:

```php
use Spatie\Permission\Models\Permission;

Permission::create(['name' => 'sis.identifier.reserve']);
Permission::create(['name' => 'sis.identifier.commission']);
Permission::create(['name' => 'sis.identifier.commission.adiq']);   // scope-aware: ADIQ only

$user->givePermissionTo('sis.identifier.commission.adiq');
```

The resolver checks the scoped permission (`sis.identifier.commission.adiq`) first, then falls back to the bare ability (`sis.identifier.commission`). A typo in a permission string is a **denial, not a silent allow** — a Spatie exception on a missing permission is caught and treated as deny.

Verify what an actor can do:

```bash
php artisan sis:permissions --actor=user:1
```

Remember: authorization decides *who may ask*, never *what is legal* — no Spatie permission can authorize an illegal operation. See [authorization](../tools/authorization.md).

---

[← Docs index](../../README.md#documentation)
