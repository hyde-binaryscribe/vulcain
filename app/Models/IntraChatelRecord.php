<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;

/**
 * Fiche d'un référentiel intra-Chatel (copie en lecture seule) et son
 * association éventuelle à l'équivalent Vulkain.
 */
class IntraChatelRecord extends Model
{
    use BelongsToOrganisation;

    public const KIND_AGENT = 'agent';

    public const KIND_VEHICLE = 'vehicle';

    public const KIND_BAG = 'bag';

    public const KIND_OXYGEN_BOTTLE = 'oxygen_bottle';

    public const LINK_AUTO = 'auto';

    public const LINK_MANUAL = 'manual';

    protected $fillable = ['kind', 'external_id', 'label', 'data', 'linked_id', 'link_mode', 'synced_at', 'missing_since'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'linked_id' => 'integer',
            'synced_at' => 'datetime',
            'missing_since' => 'datetime',
        ];
    }

    /** Équivalent Vulkain associé, ou null. */
    public function linked(): ?Model
    {
        if ($this->linked_id === null) {
            return null;
        }
        $class = self::linkedClass($this->kind);

        return $class::query()->find($this->linked_id);
    }

    /** @return class-string<Model> */
    public static function linkedClass(string $kind): string
    {
        return match ($kind) {
            self::KIND_AGENT => User::class,
            self::KIND_VEHICLE => Vehicle::class,
            self::KIND_BAG => Location::class,
            self::KIND_OXYGEN_BOTTLE => OxygenBottle::class,
        };
    }

    /** Fiche intra-Chatel d'un type, associée, retrouvée par un champ publié (insensible à la casse). */
    public static function linkedIdBy(string $kind, string $field, string $value): ?int
    {
        $value = mb_strtolower(trim($value));
        if ($value === '') {
            return null;
        }

        $ids = self::query()->where('kind', $kind)->whereNotNull('linked_id')->whereNull('missing_since')->get()
            ->filter(fn (self $r) => mb_strtolower(trim((string) ($r->data[$field] ?? ''))) === $value)
            ->pluck('linked_id')->unique();

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }
}
