<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OwnerInvitationNotification extends Notification
{
    public function __construct(public string $studioName, public string $acceptUrl) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Poziv za vlasnika studija')
            ->greeting('Poziv za upravljanje studijem')
            ->line('Pozvani ste kao vlasnik studija: '.$this->studioName)
            ->line('Poveznica vrijedi sedam dana i može se iskoristiti jednom. Nakon prijave obvezno je postavljanje 2FA.')
            ->action('Prihvati poziv', $this->acceptUrl);
    }
}
