<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Lien de réinitialisation de mot de passe (URL déjà construite sur le
 * sous-domaine de l'organisation). Envoi synchrone.
 */
class ResetPasswordNotification extends Notification
{
    public function __construct(public string $resetUrl) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('security.password_reset.expires_minutes', 60);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — Vulcain')
            ->greeting('Bonjour,')
            ->line('Vous avez demandé la réinitialisation de votre mot de passe.')
            ->action('Réinitialiser le mot de passe', $this->resetUrl)
            ->line("Ce lien expirera dans {$minutes} minutes et ne peut être utilisé qu'une seule fois.")
            ->line("Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail.");
    }
}
