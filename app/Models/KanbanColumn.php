<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Colonne d'un tableau Kanban.
 */
class KanbanColumn extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'kanban_board_id',
        'name',
        'display_order',
        'is_done',
    ];

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    public function board(): BelongsTo
    {
        return $this->belongsTo(KanbanBoard::class, 'kanban_board_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
