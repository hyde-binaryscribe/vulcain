<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Notifications\Notification;

/**
 * Notifie un utilisateur qu'un événement (anomalie / réparation) lui a été assigné.
 */
class EventAssigned extends Notification
{
    public function __construct(private readonly Event $event) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'title' => $this->event->title,
            'message' => 'Un événement vous a été assigné : '.$this->event->title,
            'url' => '/events',
        ];
    }
}
