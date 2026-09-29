<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Journal de consultation d'un document (qui, quand, motif — ex. contrôle routier).
 */
class DocumentAccess extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'document_id',
        'user_id',
        'reason',
        'consulted_at',
    ];

    protected function casts(): array
    {
        return [
            'consulted_at' => 'datetime',
        ];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
