<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;

/**
 * Enregistre automatiquement les actions (création / modification / suppression)
 * d'un modèle dans le journal d'activité, avec l'acteur et les valeurs avant/après.
 */
trait RecordsActivity
{
    /** Champs jamais journalisés (bruit ou sensibles). */
    protected array $activityExcluded = ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'];

    public static function bootRecordsActivity(): void
    {
        static::created(fn ($model) => $model->recordActivity('created'));
        static::updated(fn ($model) => $model->recordActivity('updated'));
        static::deleted(fn ($model) => $model->recordActivity('deleted'));
    }

    public function recordActivity(string $action): void
    {
        $properties = null;

        if ($action === 'updated') {
            $changed = array_diff(array_keys($this->getChanges()), $this->activityExcluded);

            if ($changed === []) {
                return;
            }

            $properties = [
                'old' => array_intersect_key($this->getOriginal(), array_flip($changed)),
                'new' => array_intersect_key($this->getAttributes(), array_flip($changed)),
            ];
        }

        $actor = Auth::user();

        ActivityLog::create([
            'organisation_id' => $this->getAttribute('organisation_id') ?? app(TenantContext::class)->id(),
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name ?? 'Système',
            'subject_type' => $this->getMorphClass(),
            'subject_id' => $this->getKey(),
            'action' => $action,
            'description' => $this->activityLabel(),
            'properties' => $properties,
            'created_at' => now(),
        ]);
    }

    /** Libellé de la ressource pour l'historique (surchargeable). */
    public function activityLabel(): string
    {
        return $this->getAttribute('name')
            ?? $this->getAttribute('reference')
            ?? $this->getAttribute('serial_number')
            ?? $this->getAttribute('lot_number')
            ?? ('#'.$this->getKey());
    }
}
