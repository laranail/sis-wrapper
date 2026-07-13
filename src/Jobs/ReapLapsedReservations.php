<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Simtabi\Laranail\SIS\Actions\VoidReservation;
use Simtabi\Laranail\SIS\Authorization\ActorResolver;
use Simtabi\Laranail\SIS\Data\CommandContext;
use Simtabi\Laranail\SIS\Models\SisRecord;
use Simtabi\SIS\Enums\LifecycleState;

/**
 * Voids reservations that passed their expiry and were never claimed (§6.5). It only ever touches RESERVED
 * rows — a commissioned identifier is never reaped. Each void goes through the registrar (authorized,
 * audited) as the system actor.
 */
final class ReapLapsedReservations extends SisJob
{
    public function handle(VoidReservation $void, ActorResolver $actors): void
    {
        SisRecord::query()
            ->where('state', LifecycleState::Reserved->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Date::now())
            ->get()
            ->each(function (SisRecord $record) use ($void, $actors): void {
                $void(
                    $record->identifier(),
                    'reservation lapsed and was never claimed',
                    new CommandContext($actors->system(), CarbonImmutable::now(), Str::uuid()->toString(), Str::uuid()->toString()),
                );
            });
    }
}
