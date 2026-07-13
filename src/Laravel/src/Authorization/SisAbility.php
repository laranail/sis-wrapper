<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Authorization;

/**
 * The canonical ability list — a PUBLIC CONTRACT. These strings appear in consumers' permission tables and
 * role definitions, so they are governed exactly like class codes and morph aliases: allocated once, never
 * reassigned, retired with the thing they name. Renaming one silently revokes access in every app that
 * stored it.
 *
 * `Reserve` is gated HARDER than the rest and should be granted to fewer actors than `Commission`:
 * reserving burns a serial permanently, and serials are never reused, so an actor who can reserve in a
 * loop can exhaust the space forever. It is the most dangerous ability in the package, not the safest.
 */
enum SisAbility: string
{
    case ViewRegister = 'sis.register.view';
    case ViewAudit = 'sis.audit.view';
    case Reserve = 'sis.identifier.reserve';
    case Mint = 'sis.identifier.mint';
    case Commission = 'sis.identifier.commission';
    case AttachSubject = 'sis.identifier.attach-subject';
    case Suspend = 'sis.identifier.suspend';
    case Restore = 'sis.identifier.restore';
    case Decommission = 'sis.identifier.decommission';
    case Supersede = 'sis.identifier.supersede';
    case Release = 'sis.identifier.release';
    case VerifyIntegrity = 'sis.register.verify';
    case Backfill = 'sis.register.backfill';
    case ManageWebhooks = 'sis.webhooks.manage';
}
