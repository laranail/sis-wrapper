# Translations

Every user-facing string the package emits is a Laravel translation key, so a consuming app can localise or reword it without touching the source.

The strings live in `resources/lang/en/` and load under the `sis::` namespace (registered by `SisServiceProvider` via package-tools' `hasTranslations('sis')`). The full `laranail/sis-wrapper::` namespace resolves the same files, so `__('sis::messages.problem.409')` and `__('laranail/sis-wrapper::messages.problem.409')` are equivalent. Developer-facing text — exception messages and the `[sis:*]` machine tags the constraint translator parses — is deliberately *not* translated, so log-scraping and error mapping stay stable across locales.

## What is translatable

| Surface | File · group | Example key |
|---------|--------------|-------------|
| Validation rule messages (the 10 `Rules/*`) | `validation.php` | `sis::validation.invalid_identifier` |
| Console output (`sis:install`, `sis:doctor`, `sis:permissions`) | `messages.php` · `commands.*` | `sis::messages.commands.doctor.schema_present` |
| The serial-capacity notification | `messages.php` · `notifications.serial_capacity` | `sis::messages.notifications.serial_capacity.advice` |
| RFC 9457 problem titles | `messages.php` · `problem` | `sis::messages.problem.409` |

Problem titles are keyed by HTTP status; `ProblemRenderer` falls back to `problem.default` (`Bad Request`) for an unlisted status. The problem `detail` stays the exception's own message and is not translated.

## Placeholders

The strings use Laravel's `:name` placeholders. `:attribute` is filled by the validator; the rest are passed at the call site: `:class` (a class label), `:from` (a lifecycle state), `:where` and `:percent` (the capacity notification), and the console commands' own tokens (`:driver`, `:count`, `:tables`, …). Console markup (`<info>`, `<comment>`, `<fg=red>`) and the `[OK]` / `[WARN]` / `[FAIL]` prefixes are part of the strings.

## Overriding the strings or adding a locale

Publish the English files, then edit them or add a sibling locale directory:

```bash
php artisan vendor:publish --tag=laranail::sis-wrapper-translations
```

They land in `lang/vendor/sis/en/{validation,messages}.php`. A published key overrides the packaged one; an unpublished key falls through to the package default, so you only copy what you change. Add `lang/vendor/sis/{locale}/…` for another language — the active locale (`app()->getLocale()`) selects the file. Translations are opt-in overrides and are **not** published by `sis:install`; publish them only when you want to customise.

---

[← Docs index](../../README.md#documentation)
