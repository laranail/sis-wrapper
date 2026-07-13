<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

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
 * The human labels come from `laranail/enumerator`: each case carries a `#[Label]` attribute and the
 * `HasEnumeratorBehavior` trait exposes `label()`, `labels()`, and `options()` — so panels and the
 * `sis:permissions` command present abilities without hand-formatting the enum names.
 */
enum SisAbility: string implements Enumerator
{
    use HasEnumeratorBehavior;

    #[Label('View register')]
    case ViewRegister = 'sis.register.view';

    #[Label('View audit trail')]
    case ViewAudit = 'sis.audit.view';

    #[Label('Reserve identifier')]
    case Reserve = 'sis.identifier.reserve';

    #[Label('Mint identifier')]
    case Mint = 'sis.identifier.mint';

    #[Label('Commission identifier')]
    case Commission = 'sis.identifier.commission';

    #[Label('Attach subject')]
    case AttachSubject = 'sis.identifier.attach-subject';

    #[Label('Suspend identifier')]
    case Suspend = 'sis.identifier.suspend';

    #[Label('Restore identifier')]
    case Restore = 'sis.identifier.restore';

    #[Label('Decommission identifier')]
    case Decommission = 'sis.identifier.decommission';

    #[Label('Supersede identifier')]
    case Supersede = 'sis.identifier.supersede';

    #[Label('Release reservation')]
    case Release = 'sis.identifier.release';

    #[Label('Verify register integrity')]
    case VerifyIntegrity = 'sis.register.verify';

    #[Label('Backfill register')]
    case Backfill = 'sis.register.backfill';

    #[Label('Manage webhooks')]
    case ManageWebhooks = 'sis.webhooks.manage';
}
