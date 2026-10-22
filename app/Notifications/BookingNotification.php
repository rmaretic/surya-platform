<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Symfony\Component\Mime\Email;

class BookingNotification extends Notification
{
    /** @param list<string> $lines */
    public function __construct(public string $subject, public string $studioName, public string $portalUrl, public array $lines, public int $eventId, public int $tenantId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->from(config('mail.from.address'), $this->studioName)
            ->subject($this->subject)
            ->view(['html' => 'booking-notification', 'text' => 'booking-notification-text'], [
                'heading' => $this->subject, 'studioName' => $this->studioName,
                'portalUrl' => $this->portalUrl, 'lines' => $this->lines,
            ])->withSymfonyMessage(function (Email $message): void {
                $message->getHeaders()->addTextHeader('X-Surya-Outbox', $this->tenantId.':'.$this->eventId);
            });
    }
}
