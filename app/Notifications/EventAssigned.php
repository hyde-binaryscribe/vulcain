<?php

namespace App\Notifications;

use App\Domain\Support\Severity;
use App\Models\Event;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie un utilisateur qu'un événement (anomalie / réparation) lui a été
 * assigné (in-app + e-mail).
 */
class EventAssigned extends Notification
{
    public function __construct(private readonly Event $event) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $high = ((string) $this->event->priority) === 'haute';
        $label = $this->event->type->label();

        $mail = (new MailMessage)
            ->subject("{$label} — {$this->event->title}")
            ->greeting('Bonjour,')
            ->line("Un événement vous a été assigné : **{$this->event->title}**.");

        if ($this->event->vehicle) {
            $mail->line('Véhicule : '.($this->event->vehicle->callsign ?: $this->event->vehicle->name).'.');
        }
        $mail->line('Priorité : '.$this->event->priority.'.')
            ->action('Ouvrir', url('/events'));

        return $high ? $mail->error() : $mail;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'title' => $this->event->title,
            'message' => 'Un événement vous a été assigné : '.$this->event->title,
            'url' => '/events',
            // Niveau d'urgence (rouge/orange/jaune) dérivé de la priorité.
            'level' => Severity::fromEventPriority((string) $this->event->priority)->value,
        ];
    }
}
