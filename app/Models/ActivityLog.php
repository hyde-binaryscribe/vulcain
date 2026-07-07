<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Entrée du journal d'activité. Écrite via le trait RecordsActivity ;
 * jamais modifiée depuis l'interface (base de l'audit).
 */
class ActivityLog extends Model
{
    use BelongsToOrganisation;

    public $timestamps = false;

    protected $fillable = [
        'organisation_id',
        'user_id',
        'actor_name',
        'subject_type',
        'subject_id',
        'action',
        'description',
        'properties',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Filtre par ensembles de sujets : [MorphClass => [ids]].
     *
     * @param  Builder<ActivityLog>  $query
     * @param  array<string, array<int>>  $map
     */
    public function scopeForSubjects($query, array $map)
    {
        return $query->where(function ($q) use ($map) {
            foreach ($map as $type => $ids) {
                if ($ids === []) {
                    continue;
                }
                $q->orWhere(fn ($w) => $w->where('subject_type', $type)->whereIn('subject_id', $ids));
            }
        });
    }
}
