<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Document rattaché à un véhicule (agrément, CT, carte grise…) ou à un
 * utilisateur (diplôme, autorisation ARS, permis…). Fichier sur le disque privé.
 */
class Document extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    protected $fillable = [
        'category',
        'title',
        'file_path',
        'mime',
        'size',
        'expires_at',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'date',
            'size' => 'integer',
        ];
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function accesses(): HasMany
    {
        return $this->hasMany(DocumentAccess::class);
    }
}
