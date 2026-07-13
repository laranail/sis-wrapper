<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Enums;

use Simtabi\Laranail\Enumerator\Attributes\Description;
use Simtabi\Laranail\Enumerator\Attributes\Label;
use Simtabi\Laranail\Enumerator\Concerns\HasEnumeratorBehavior;
use Simtabi\Laranail\Enumerator\Contracts\Enumerator;

/**
 * The canonical ability list — a PUBLIC CONTRACT. These strings appear in consumers' permission tables and
 * role definitions, so they are governed exactly like class codes and morph aliases: allocated once, never
 * reassigned, retired with the thing they name. Renaming one silently revokes access in every app that
 * stored it.
 *
 * `Reserve` is gated HARDER than the rest and should be granted to fewer actors than `Commission`:
 * reserving burns a serial permanently, and serials are never reused, so an actor who can reserve in a
 * loop can exhaust the space forever. It is the most dangerous ability in the package, not the safest.
 *
 * Each case carries its human `#[Label]` and an operator-facing `#[Description]` (of *what the ability
 * governs and how dangerous it is*) via `laranail/enumerator`; the `HasEnumeratorBehavior` trait exposes
 * `label()`, `description()`, `labels()`, and `options()` — so panels and the `sis:permissions` command
 * present abilities, and explain them, without hand-formatting the enum names.
 */
enum SisAbility: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('View register')]
    #[Description('Read identifiers and their lifecycle state from the register.')]
    case ViewRegister = 'sis.register.view';

    #[Label('View audit trail')]
    #[Description('Read the append-only audit trail of who did what.')]
    case ViewAudit = 'sis.audit.view';

    #[Label('Reserve identifier')]
    #[Description('Allocate an identifier, permanently burning a serial. The most dangerous ability — serials are never reused, so an actor who can reserve in a loop can exhaust the space forever. Grant to the fewest actors.')]
    case Reserve = 'sis.identifier.reserve';

    #[Label('Mint identifier')]
    #[Description('Construct an identifier value without touching the register — no serial is burned.')]
    case Mint = 'sis.identifier.mint';

    #[Label('Commission identifier')]
    #[Description('Bring a reserved identifier into active service.')]
    case Commission = 'sis.identifier.commission';

    #[Label('Attach subject')]
    #[Description('Bind an identifier to the domain model it names.')]
    case AttachSubject = 'sis.identifier.attach-subject';

    #[Label('Suspend identifier')]
    #[Description('Temporarily take a commissioned identifier out of service.')]
    case Suspend = 'sis.identifier.suspend';

    #[Label('Restore identifier')]
    #[Description('Return a suspended identifier to active service.')]
    case Restore = 'sis.identifier.restore';

    #[Label('Decommission identifier')]
    #[Description('Permanently retire an identifier from service.')]
    case Decommission = 'sis.identifier.decommission';

    #[Label('Supersede identifier')]
    #[Description('Replace an identifier with a successor, chaining the old to the new.')]
    case Supersede = 'sis.identifier.supersede';

    #[Label('Release reservation')]
    #[Description('Void a reservation before it is commissioned.')]
    case Release = 'sis.identifier.release';

    #[Label('Verify register integrity')]
    #[Description('Run the register integrity checks — audit hash chain and check characters.')]
    case VerifyIntegrity = 'sis.register.verify';

    #[Label('Backfill register')]
    #[Description('Import pre-SIS identifiers as grandfathered register rows.')]
    case Backfill = 'sis.register.backfill';

    #[Label('Manage webhooks')]
    #[Description('Create, rotate, and remove webhook endpoints.')]
    case ManageWebhooks = 'sis.webhooks.manage';

    /**
     * The ability an audit `action` string implies, for recording on the audit row. The action strings are
     * the deciders' own vocabulary (`Simtabi\SIS\Decider\*`): `reserve`, `commission`, `supersede`,
     * `release`, `void`, `attach-subject`, and `transition:<state>` (built as `'transition:' . $to->value`).
     * A `void` maps to `Release` (voiding a reservation is the release ability). An action with no ability —
     * e.g. the internal `authorize` marker on a deny row — returns null.
     */
    public static function forAuditAction(string $action): ?self
    {
        return match ($action) {
            'reserve' => self::Reserve,
            'commission' => self::Commission,
            'supersede' => self::Supersede,
            'release', 'void' => self::Release,
            'attach-subject' => self::AttachSubject,
            'transition:suspended' => self::Suspend,
            'transition:decommissioned' => self::Decommission,
            'transition:commissioned' => self::Restore,
            default => null,
        };
    }
}
