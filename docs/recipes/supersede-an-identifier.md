# Supersede an identifier

Record that a successor replaces an identifier — the only way to "correct" a commissioned record.

Identifiers and the records that carry them are never edited (§8). A wrong invoice is credited and reissued under a new one; the old record gets `superseded_by` set, and the chain of supersession *is* the audit trail. The decider requires the successor to exist and rejects a cycle.

```php
use Simtabi\Laranail\SIS\Facades\Sis;
use Simtabi\SIS\Enums\SimClass;

$wrong = Sis::parse('SIM-INV-ADIQ-000001-VY');

// Reserve + commission the replacement first...
$corrected = Sis::reserve(SimClass::INVOICE, scope: 'ADIQ', reason: 'reissue of 000001');
Sis::commission($corrected);

// ...then record the supersession. Returns the successor.
Sis::supersede($wrong, $corrected);
```

Over HTTP:

```bash
curl -X POST https://app.test/api/sis/v1/identifiers/SIM-INV-ADIQ-000001-VY/supersede \
  -H 'Idempotency-Key: 2b7d…f4' -H 'Content-Type: application/json' \
  -d '{"successor":"SIM-INV-ADIQ-000002-K8"}'
```

Walk the chain forward to the terminal successor:

```php
Sis::chain($wrong);              // [SIM-INV-ADIQ-000001-VY, SIM-INV-ADIQ-000002-K8, ...]
Sis::terminalSuccessor($wrong);  // the current, live identifier
```

Or `GET /identifiers/SIM-INV-ADIQ-000001-VY/chain`. See [the class register and lifecycle](../tools/register.md).

---

[← Docs index](../../README.md#documentation)
