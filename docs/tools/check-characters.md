# Check characters

`CheckCharacters` computes and verifies the two trailing characters of every identifier using ISO 7064 MOD 1271-36 (§4).

## The algorithm

The check is **ISO 7064 MOD 1271-36**, the pure double-character system over the 36-character alphabet `0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ`:

```
M = 1271, r = 36
P = 0
for each character c of the payload:
    P = ((P + value(c)) * r) mod M
P = (P * r) mod M          // the second shift — this is what makes it a two-character check
check = (M + 1 - P) mod M
c1 = ALPHABET[check div r]
c2 = ALPHABET[check mod r]
```

The payload is the identifier with separators removed and the check characters excluded. Case and separators are irrelevant — the payload is normalised to uppercase alphanumerics first.

## The API

`CheckCharacters` is a stateless helper with two methods:

```php
use Simtabi\SIS\Support\CheckCharacters;

CheckCharacters::for('SIM-INV-ADIQ-000001');           // 'VY' — the two check characters
CheckCharacters::verify('SIM-INV-ADIQ-000001', 'VY');  // true  (constant-time compare)
CheckCharacters::verify('SIM-INV-ADIQ-000001', 'XX');  // false
```

`verify()` compares in constant time (`hash_equals`). An illegal character in the payload raises `IllegalCharacterException`. You rarely call this directly — `Identifier::parse()` verifies the check on every parse, and `Identifier::mint()` derives it (the check is derived, never supplied).

## Why two characters, not one

Three algorithms were implemented and tested **exhaustively** over every identifier shape in the register (§4.2):

| Algorithm | Check size | Substitution | Adjacent transposition | Verdict |
|-----------|:----------:|:------------:|:----------------------:|---------|
| ISO 7064 MOD 37,36 | 1 char | 100% | 99.06% | Rejected |
| ISO 7064 MOD 97-10 (IBAN) | 2 digits | 99.49% | 100% | Rejected |
| ISO 7064 MOD 1271-36 | 2 chars | 100% | 100% | **Adopted** |

MOD 37,36 fails adjacent transpositions (`SIM-PRS-100001` and `SIM-PRS-100010` collide). MOD 97-10 expands each letter into two digits, so a single substituted letter can cancel. MOD 1271-36 detects 100% of single-character substitution, adjacent transposition, jump transposition (`aXb`→`bXa`), and twin errors (`aa`→`bb`) — proven by the property-based test in the suite.

> The two rejected algorithms were measured and failed; do not re-propose MOD 37,36 or MOD 97-10. A future edition proposing a new algorithm must publish comparable exhaustive figures and must not change the algorithm for identifiers already issued.

## Requirement

Every identifier must carry check characters. An identifier presented without a valid check is **rejected, not repaired** — `Identifier::parse()` throws `CheckCharacterMismatchException`, and `Identifier::isValid()` returns `false`.

---

[← Docs index](../../README.md#documentation)
