<?php

namespace App\Models;

use App\Domain\Events\EventStatus;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tableau Kanban de gestion des événements (personnalisable, plusieurs tableaux
 * possibles).
 */
class KanbanBoard extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'name',
        'display_order',
    ];

    public function columns(): HasMany
    {
        return $this->hasMany(KanbanColumn::class)->orderBy('display_order');
    }

    /**
     * Colonne d'entrée des nouvelles anomalies : celle désignée en réglages,
     * sinon la première colonne du premier tableau. Amorce si nécessaire.
     */
    public static function entryColumnId(Organisation $organisation): ?int
    {
        static::ensureSeeded($organisation);

        $id = $organisation->anomalyEntryColumnId();
        if ($id !== null && KanbanColumn::query()->whereKey($id)->exists()) {
            return $id;
        }

        return KanbanColumn::query()->orderBy('kanban_board_id')->orderBy('display_order')->value('id');
    }

    /**
     * Amorce le tableau par défaut à partir des statuts historiques et rattache
     * les événements existants à la colonne correspondante (une seule fois).
     */
    public static function ensureSeeded(Organisation $organisation): void
    {
        if (static::query()->exists()) {
            return;
        }

        $board = static::create(['name' => 'Événements', 'display_order' => 0]);

        $map = [];
        foreach (EventStatus::ordered() as $i => $status) {
            $column = $board->columns()->create([
                'name' => $status->label(),
                'display_order' => $i,
                'is_done' => $status->isClosed(),
            ]);
            $map[$status->value] = $column->id;
        }

        // Rattache les événements existants à la colonne de leur statut.
        foreach (Event::query()->whereNull('kanban_column_id')->get(['id', 'status']) as $event) {
            $value = $event->status instanceof EventStatus ? $event->status->value : (string) $event->status;
            if (isset($map[$value])) {
                Event::query()->whereKey($event->id)->update(['kanban_column_id' => $map[$value]]);
            }
        }

        // Colonne d'entrée par défaut des anomalies : « À traiter ».
        if (empty($organisation->settings['anomaly_entry_column_id'])) {
            $organisation->settings = array_merge($organisation->settings ?? [], [
                'anomaly_entry_column_id' => $map[EventStatus::A_TRAITER->value] ?? null,
            ]);
            $organisation->save();
        }
    }
}
