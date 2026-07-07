<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation à activer un compte administrateur pour une organisation.
 */
class OrganisationInvitationNotification extends Notification
{
    public function __construct(
        public string $organisationName,
        public string $acceptUrl,
        public int $expiresMinutes,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $days = (int) round($this->expiresMinutes / (60 * 24));

        return (new MailMessage)
            ->subject("Invitation — {$this->organisationName} sur Vulcain")
            ->greeting('Bonjour,')
            ->line("Vous êtes invité(e) à rejoindre « {$this->organisationName} » sur Vulcain en tant qu'administrateur.")
            ->action('Activer mon compte', $this->acceptUrl)
            ->line("Vous définirez votre mot de passe lors de l'activation.")
            ->line("Ce lien expire dans {$days} jour(s).")
            ->line("Si vous n'attendiez pas cette invitation, ignorez cet e-mail.");
    }
}
