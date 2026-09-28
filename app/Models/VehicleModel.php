<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Modèle de véhicule : un gabarit d'emplacements configuré une seule fois.
 * À la création d'un véhicule sur ce modèle, ses emplacements (et
 * sous-emplacements) sont générés automatiquement à partir du gabarit.
 */
class VehicleModel extends Model
{
    use BelongsToOrganisation, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'name',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'display_order' => 'integer',
        ];
    }

    /** Emplacements du gabarit (tous niveaux confondus). */
    public function templateLocations(): HasMany
    {
        return $this->hasMany(VehicleModelLocation::class)->orderBy('display_order');
    }

    /**
     * Gabarit sous forme d'arbre : liste des emplacements racines, chacun
     * portant ses enfants dans « children ».
     *
     * @return list<array<string, mixed>>
     */
    public function templateTree(): array
    {
        $all = $this->templateLocations()->get();

        $build = function (?int $parentId) use (&$build, $all): array {
            return $all
                ->where('parent_id', $parentId)
                ->sortBy('display_order')
                ->values()
                ->map(fn (VehicleModelLocation $l) => [
                    'name' => $l->name,
                    'kind' => $l->kind,
                    'children' => $build($l->id),
                ])
                ->all();
        };

        return $build(null);
    }

    /**
     * Génère les emplacements d'un véhicule à partir du gabarit, en
     * préservant la hiérarchie. À appeler juste après la création du véhicule.
     */
    public function generateLocationsFor(Vehicle $vehicle): void
    {
        $templates = $this->templateLocations()->get();

        // Ancien id de gabarit -> id d'emplacement créé, pour rattacher les enfants.
        $map = [];

        // Parcours en largeur : on crée un parent avant ses enfants.
        $pending = $templates->all();
        $guard = 0;

        while ($pending !== [] && $guard++ < 500) {
            $progressed = false;

            foreach ($pending as $key => $tpl) {
                // Le parent doit déjà être créé (ou être une racine).
                if ($tpl->parent_id !== null && ! isset($map[$tpl->parent_id])) {
                    continue;
                }

                $location = Location::create([
                    'vehicle_id' => $vehicle->id,
                    'parent_id' => $tpl->parent_id !== null ? $map[$tpl->parent_id] : null,
                    'kind' => $tpl->kind,
                    'name' => $tpl->name,
                    'display_order' => $tpl->display_order,
                ]);

                $map[$tpl->id] = $location->id;
                unset($pending[$key]);
                $progressed = true;
            }

            // Garde-fou : parents manquants (données incohérentes) -> on arrête.
            if (! $progressed) {
                break;
            }
        }
    }
}
