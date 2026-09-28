<?php

namespace App\Notifications;

use App\Domain\Hr\LeaveStatus;
use App\Domain\Support\Severity;
use App\Models\LeaveRequest;
use Illuminate\Notifications\Notification;

/**
 * Notifie l'auteur d'une demande de congé de la décision prise.
 */
class LeaveDecided extends Notification
{
    public function __construct(private readonly LeaveRequest $leave) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
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
