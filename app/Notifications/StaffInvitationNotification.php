<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class StaffInvitationNotification extends Notification
{
    public function __construct(public string $studioName, public string $acceptUrl, public string $role) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Poziv u osoblje studija')
            ->greeting('Poziv u studio')
            ->line('Studio: '.$this->studioName)
            ->line('Uloga: '.($this->role === 'manager' ? 'Manager' : 'Instruktor'))
            ->line('Poveznica vrijedi sedam dana i može se iskoristiti jednom. Koristite je za postavljanje vlastite lozinke.')
            ->action('Prihvati poziv', $this->acceptUrl);
    }
}
