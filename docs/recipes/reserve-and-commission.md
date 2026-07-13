# Reserve and commission an identifier

Allocate a serial, then lock it forever — the two-step create path.

Reserving records who, when, and why, and burns a serial (§6.5); commissioning locks the identifier permanently and optionally binds its alias and subject in the same act (§6.4).

```php
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\SIS\Identifier\IdClass;
use Simtabi\SIS\Identifier\Alias;
use Simtabi\SIS\Identifier\SubjectRef;

// 1. Reserve — global class, so no scope.
$id = Sis::reserve(IdClass::Client, reason: 'onboarding AdelsaIQ');
// SIM-CLT-100001-9O, state = reserved

// 2. Commission — locks it forever; bind the alias and the subject it names.
Sis::commission(
    $id,
    alias: Alias::of('ADIQ'),
    subject: SubjectRef::of('client', '42'),   // 'client' must be a mapped morph alias
);
// state = commissioned — immutable from here
```

Over HTTP (each write needs an `Idempotency-Key`):

```bash
curl -X POST https://app.test/api/sis/v1/identifiers \
  -H 'Idempotency-Key: 1c8f…e9' -H 'Content-Type: application/json' \
  -d '{"class":"CLT","reason":"onboarding AdelsaIQ"}'

curl -X POST https://app.test/api/sis/v1/identifiers/SIM-CLT-100001-9O/commission \
  -H 'Idempotency-Key: 44a1…02' -H 'Content-Type: application/json' \
  -d '{"alias":"ADIQ","subject":{"type":"client","id":"42"}}'
```

For a Form S (scoped) class, pass the client's alias as `scope`: `Sis::reserve(IdClass::Invoice, scope: 'ADIQ', reason: 'March retainer')` → `SIM-INV-ADIQ-000001-VY`.

See [the class register and lifecycle](../tools/register.md) and [the `Sis` facade](../tools/facade.md).

---

[← Docs index](../../README.md#documentation)
