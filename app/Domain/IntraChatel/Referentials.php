<?php

namespace App\Domain\IntraChatel;

use App\Domain\Storage\LocationKind;
use App\Models\IntraChatelRecord;
use App\Models\Location;
use App\Models\OxygenBottle;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Référentiels intra-Chatel (agents, véhicules, sacs, bouteilles d'oxygène).
 *
 * Règle du lien : intra-Chatel fait référence. Vulkain lit ce qu'intra-Chatel
 * publie, en garde une copie et l'associe à ses propres fiches ; il ne modifie
 * jamais les données d'intra-Chatel, ni ses propres fiches à partir d'elles.
 *
 * Une publication est complète (instantané) : une fiche absente est marquée
 * « absente » (missing_since), jamais supprimée, et son association est gardée.
 */
class Referentials
{
    /** Publications par type : segment d'URL → type, champ obligatoire servant de libellé. */
    public const KINDS = [
        'agents' => IntraChatelRecord::KIND_AGENT,
        'vehicles' => IntraChatelRecord::KIND_VEHICLE,
        'bags' => IntraChatelRecord::KIND_BAG,
        'oxygen-bottles' => IntraChatelRecord::KIND_OXYGEN_BOTTLE,
    ];

    public const MAX_ITEMS = 2000;

    /** Règles de validation d'une publication. */
    public static function rules(string $kind): array
    {
        $common = [
            'data' => ['present', 'array', 'max:'.self::MAX_ITEMS],
            'data.*.id' => ['required', 'distinct', 'max:100'],
            'data.*.active' => ['nullable', 'boolean'],
        ];

        return $common + match ($kind) {
            IntraChatelRecord::KIND_AGENT => [
                'data.*.initials' => ['required', 'string', 'max:20'],
                'data.*.first_name' => ['nullable', 'string', 'max:100'],
                'data.*.last_name' => ['nullable', 'string', 'max:100'],
            ],
            IntraChatelRecord::KIND_VEHICLE => [
                'data.*.callsign' => ['required', 'string', 'max:50'],
                'data.*.registration' => ['nullable', 'string', 'max:20'],
                'data.*.name' => ['nullable', 'string', 'max:100'],
            ],
            IntraChatelRecord::KIND_BAG => [
                'data.*.name' => ['required', 'string', 'max:100'],
                'data.*.vehicle_id' => ['nullable', 'max:100'],
                'data.*.seal_number' => ['nullable', 'string', 'max:50'],
            ],
            IntraChatelRecord::KIND_OXYGEN_BOTTLE => [
                'data.*.serial_number' => ['required', 'string', 'max:50'],
                'data.*.capacity_l' => ['nullable', 'numeric', 'min:0', 'max:100'],
                'data.*.vehicle_id' => ['nullable', 'max:100'],
            ],
        };
    }

    /**
     * Enregistre une publication complète d'un type pour l'organisation courante.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array{received: int, created: int, updated: int, missing: int, linked: int}
     */
    public static function sync(string $kind, array $items): array
    {
        return DB::transaction(function () use ($kind, $items) {
            $now = now();
            $existing = IntraChatelRecord::query()->where('kind', $kind)->get()->keyBy('external_id');
            $seen = [];
            $created = 0;
            $updated = 0;

            foreach ($items as $item) {
                $id = (string) $item['id'];
                $seen[] = $id;
                $attributes = ['label' => self::label($kind, $item), 'data' => $item, 'synced_at' => $now, 'missing_since' => null];

                if ($record = $existing->get($id)) {
                    $record->update($attributes);
                    $updated++;
                } else {
                    IntraChatelRecord::create(['kind' => $kind, 'external_id' => $id] + $attributes);
                    $created++;
                }
            }

            $missing = IntraChatelRecord::query()->where('kind', $kind)
                ->whereNotIn('external_id', $seen)->whereNull('missing_since')
                ->update(['missing_since' => $now]);

            return [
                'received' => count($items),
                'created' => $created,
                'updated' => $updated,
                'missing' => $missing,
                'linked' => self::autoLink($kind),
            ];
        });
    }

    /**
     * Associe automatiquement les fiches sans association quand une seule fiche
     * Vulkain correspond exactement. N'écrase jamais une association existante.
     */
    public static function autoLink(string $kind): int
    {
        $linked = 0;
        $records = IntraChatelRecord::query()->where('kind', $kind)->whereNull('linked_id')->whereNull('missing_since')->get();
        $taken = IntraChatelRecord::query()->where('kind', $kind)->whereNotNull('linked_id')->pluck('linked_id')->all();

        foreach ($records as $record) {
            $match = self::match($kind, (array) $record->data)->reject(fn (int $id) => in_array($id, $taken, true));
            if ($match->count() === 1) {
                $record->update(['linked_id' => $match->first(), 'link_mode' => IntraChatelRecord::LINK_AUTO]);
                $taken[] = $match->first();
                $linked++;
            }
        }

        return $linked;
    }

    /** Fiches Vulkain proposables pour l'association manuelle : [id => libellé]. */
    public static function candidates(string $kind): Collection
    {
        return match ($kind) {
            IntraChatelRecord::KIND_AGENT => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'username'])
                ->mapWithKeys(fn (User $u) => [$u->id => $u->name.($u->username ? " ({$u->username})" : '')]),
            IntraChatelRecord::KIND_VEHICLE => Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign', 'registration'])
                ->mapWithKeys(fn (Vehicle $v) => [$v->id => trim(($v->callsign ? "{$v->callsign} — " : '').$v->name.($v->registration ? " ({$v->registration})" : ''))]),
            IntraChatelRecord::KIND_BAG => self::bags()->orderBy('name')->get(['id', 'name'])
                ->mapWithKeys(fn (Location $l) => [$l->id => $l->name]),
            IntraChatelRecord::KIND_OXYGEN_BOTTLE => OxygenBottle::query()->with('vehicle:id,name,callsign')->orderBy('serial_number')->get()
                ->mapWithKeys(fn (OxygenBottle $b) => [$b->id => ($b->serial_number ?: $b->label).($b->vehicle ? ' — '.($b->vehicle->callsign ?: $b->vehicle->name) : '')]),
        };
    }

    /** Libellé lisible d'une fiche publiée. */
    public static function label(string $kind, array $item): string
    {
        $label = match ($kind) {
            IntraChatelRecord::KIND_AGENT => trim($item['initials'].' — '.trim(($item['first_name'] ?? '').' '.($item['last_name'] ?? '')), ' —'),
            IntraChatelRecord::KIND_VEHICLE => trim($item['callsign'].(! empty($item['registration']) ? " ({$item['registration']})" : '')),
            IntraChatelRecord::KIND_BAG => (string) $item['name'],
            IntraChatelRecord::KIND_OXYGEN_BOTTLE => (string) $item['serial_number'],
        };

        return mb_substr($label, 0, 150);
    }

    /** Ids Vulkain correspondant exactement à une fiche publiée. */
    private static function match(string $kind, array $item): Collection
    {
        $eq = fn (Builder $q, string $column, ?string $value) => $q->whereRaw("lower({$column}) = ?", [mb_strtolower(trim((string) $value))]);

        $ids = match ($kind) {
            IntraChatelRecord::KIND_AGENT => $eq(User::query()->where('is_active', true), 'username', $item['initials'] ?? '')->pluck('id'),
            IntraChatelRecord::KIND_VEHICLE => ($byCallsign = $eq(Vehicle::query(), 'callsign', $item['callsign'] ?? '')->pluck('id'))->isNotEmpty() || empty($item['registration'])
                ? $byCallsign
                : Vehicle::query()->get(['id', 'registration'])
                    ->filter(fn (Vehicle $v) => self::plate($v->registration) === self::plate($item['registration']))
                    ->pluck('id'),
            IntraChatelRecord::KIND_BAG => $eq(self::bags(), 'name', $item['name'] ?? '')->pluck('id'),
            IntraChatelRecord::KIND_OXYGEN_BOTTLE => $eq(OxygenBottle::query(), 'serial_number', $item['serial_number'] ?? '')->pluck('id'),
        };

        return $ids->map(fn ($id) => (int) $id)->values();
    }

    private static function bags(): Builder
    {
        return Location::query()->where('kind', LocationKind::SAC->value);
    }

    /** Immatriculation sans tirets ni espaces, en majuscules. */
    private static function plate(?string $value): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', (string) $value));
    }
}
