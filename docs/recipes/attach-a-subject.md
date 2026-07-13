# Attach a subject

Bind the thing an identifier names to a still-reserved identifier.

The subject is a polymorphic reference stored as a morph **alias** plus an id — never a fully-qualified class name (§2.5, §9). Map the alias in config first, then attach; once commissioned the subject is frozen, and the decider enforces "one thing, one identifier".

```php
// config/sis.php
'morph' => [
    'aliases' => [
        'client'  => \App\Models\Client::class,
        'invoice' => \App\Models\Invoice::class,
    ],
    'record_in_database' => true,
],
```

```php
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\SIS\Identifier\SubjectRef;

$id = Sis::reserve(\Simtabi\SIS\Identifier\IdClass::Client, reason: 'onboarding AdelsaIQ');

Sis::attachSubject($id, SubjectRef::of('client', '42'));   // alias 'client', id 42
```

Over HTTP:

```bash
curl -X POST https://app.test/api/sis/v1/identifiers/SIM-CLT-100001-9O/subject \
  -H 'Idempotency-Key: 9f0c…71' -H 'Content-Type: application/json' \
  -d '{"type":"client","id":"42"}'
```

An unmapped `type` is rejected — at the HTTP edge by the `KnownMorphAlias` rule, and at the storage layer because `SisMorphServiceProvider` calls `Relation::enforceMorphMap()` at boot. A raw class name never crosses the wire. Reverse-lookup with `Sis::resolveSubject(SubjectRef::of('client', '42'))` or `GET /subjects?type=client&id=42`.

See [configuration → morph map](../configuration.md#morph-map) and [architecture → enforced morph map](../architecture.md#enforced-morph-map).

---

[← Docs index](../../README.md#documentation)
