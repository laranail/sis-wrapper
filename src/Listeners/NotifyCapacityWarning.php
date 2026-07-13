<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Listeners;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Simtabi\Laranail\SIS\Events\SerialSpaceNearingExhaustion;
use Simtabi\Laranail\SIS\Notifications\SerialSpaceNearingExhaustionNotification;

/**
 * Sends the capacity warning — but only when notifications are enabled and a recipient is configured. Off
 * by default; a package that emails on install is a package nobody keeps installed.
 */
final class NotifyCapacityWarning
{
    public function handle(SerialSpaceNearingExhaustion $event): void
    {
        if (!Config::boolean('sis.notifications.enabled', false)) {
            return;
        }

        $recipient = config('sis.notifications.recipient');

        if (!is_string($recipient) || $recipient === '') {
            return;
        }

        Notification::route('mail', $recipient)
            ->notify(new SerialSpaceNearingExhaustionNotification($event));
    }
}
