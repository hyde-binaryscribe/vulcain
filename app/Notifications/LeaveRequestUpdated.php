<?php

namespace App\Notifications;

use App\Domain\Support\Severity;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Prévient les responsables (permission leave.manage) qu'un agent a annulé ou
 * modifié sa propre demande de congé. Une modification renvoie la demande en
 * attente de validation : la mention « modifiée » évite une validation par erreur.
 */
class LeaveRequestUpdated extends Notification
{
    public const CANCELLED = 'cancelled';

    public const MODIFIED = 'modified';

    public function __construct(
        private readonly LeaveRequest $leave,
        private readonly string $kind,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $cancelled = $this->kind === self::CANCELLED;
        $author = $this->leave->user?->name ?? 'Un agent';
        $type = $this->leave->type->label();
        $period = 'du '.$this->leave->start_date->format('d/m/Y').' au '
            .$this->leave->end_date->format('d/m/Y').' ('.$this->leave->days().' jour(s))';

        $mail = (new MailMessage)
            ->subject($cancelled
                ? "Congé annulé — {$author}"
                : "Congé modifié à revalider — {$author}")
            ->greeting('Bonjour,');

        if ($cancelled) {
            $mail->line("{$author} a **annulé** sa demande de {$type}.")
                ->line('Période concernée : '.$period.'.');
        } else {
            $mail->line("{$author} a **modifié** sa demande de {$type} : elle est repassée **en attente de validation**.")
                ->line('Nouvelle période : '.$period.'.')
                ->line('Vérifiez les nouvelles dates avant de valider.');
        }

        return $mail->action('Voir les congés', url('/leave'))
            ->salutation("L'équipe Vulkain");
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $cancelled = $this->kind === self::CANCELLED;
        $author = $this->leave->user?->name ?? 'Un agent';

        return [
            'leave_id' => $this->leave->id,
            'message' => $cancelled
                ? "{$author} a annulé sa demande de ".$this->leave->type->label().'.'
                : "{$author} a modifié sa demande de ".$this->leave->type->label().' — à revalider.',
            'url' => '/leave',
            'level' => $cancelled ? Severity::WATCH->value : Severity::WARNING->value,
        ];
    }
}
