<?php

namespace App\Notifications;

use App\Domain\Hr\LeaveStatus;
use App\Domain\Support\Severity;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'auteur d'une demande de congé de la décision prise (in-app + e-mail).
 */
class LeaveDecided extends Notification
{
    public function __construct(private readonly LeaveRequest $leave) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->leave->status === LeaveStatus::APPROVED;
        $type = $this->leave->type->label();
        $firstName = trim(explode(' ', (string) ($notifiable->name ?? ''))[0]);

        $mail = (new MailMessage)
            ->subject("Demande de {$type} — ".($approved ? 'acceptée' : 'refusée'))
            ->greeting('Bonjour'.($firstName !== '' ? " {$firstName}" : '').',')
            ->line("Votre demande de {$type} a été ".($approved ? '**acceptée**' : '**refusée**')
                .($this->leave->reviewer ? ' par '.$this->leave->reviewer->name : '').'.')
            ->line('Période : du '.$this->leave->start_date->format('d/m/Y').' au '
                .$this->leave->end_date->format('d/m/Y').' ('.$this->leave->days().' jour(s)).')
            ->action('Voir mes congés', url('/leave'));

        return $approved ? $mail->success() : $mail->error();
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $approved = $this->leave->status === LeaveStatus::APPROVED;

        return [
            'leave_id' => $this->leave->id,
            'message' => 'Votre demande de '.$this->leave->type->label().' a été '
                .($approved ? 'approuvée' : 'refusée').'.',
            'url' => '/leave',
            'level' => $approved ? Severity::WATCH->value : Severity::WARNING->value,
        ];
    }
}
