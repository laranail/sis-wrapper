<?php

declare(strict_types=1);

namespace Simtabi\Laranail\SIS\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Simtabi\Laranail\SIS\Events\SerialSpaceNearingExhaustion;

/**
 * A capacity warning, delivered as a real Laravel notification so a consumer can route it. Actionable:
 * widen the serial (always safe). Off by default; the channels come from config.
 */
final class SerialSpaceNearingExhaustionNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly SerialSpaceNearingExhaustion $event,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = config('sis.notifications.channels', ['mail']);

        return is_array($channels)
            ? array_values(array_filter($channels, 'is_string'))
            : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $where = $this->event->scope !== null
            ? "{$this->event->class} scoped to {$this->event->scope}"
            : $this->event->class;

        return (new MailMessage)
            ->subject(__('sis::messages.notifications.serial_capacity.subject'))
            ->line(__('sis::messages.notifications.serial_capacity.usage', [
                'where' => $where,
                'percent' => (int) round($this->event->usage * 100),
            ]))
            ->line(__('sis::messages.notifications.serial_capacity.advice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'class' => $this->event->class,
            'scope' => $this->event->scope,
            'usage' => $this->event->usage,
        ];
    }
}
