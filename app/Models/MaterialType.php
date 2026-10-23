<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Type de matériel (Thermomètre, Scope, Compresse 5×5…). Porte le mode de suivi
 * commun à tous ses modèles : durable (n° de série), consommable (lot), ou
 * simple quantité.
 */
class MaterialType extends Model
{
    use BelongsToOrganisation, HasFactory, RecordsActivity, SoftDeletes;

    protected $fillable = [
        'category_id',
        'name',
        'code',
        'tracking_mode',
        'display_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MaterialType $t) {
            if (empty($t->code)) {
                $t->code = static::generateCode($t->name);
            }
        });
    }

    /**
     * Génère un code court unique (par organisation) depuis le nom :
     * consonnes en majuscules, 3 lettres, suffixe numérique si collision.
     */
    public static function generateCode(string $name): string
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii($name)));
        $cons = preg_replace('/[AEIOUY]/', '', $letters);
        $base = substr(($cons !== '' ? $cons : $letters), 0, 3);
        if ($base === '') {
            $base = 'MAT';
        }

        $taken = static::query()->whereNotNull('code')->pluck('code')->map(fn ($c) => strtoupper($c))->all();
        $code = $base;
        $i = 1;
        while (in_array($code, $taken, true)) {
            $code = $base.$i;
            $i++;
            if ($i > 999) {
                break;
            }
        }

        return $code;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MaterialCategory::class, 'category_id');
    }

    /** Modèles rattachés à ce type (marque + modèle). */
    public function models(): HasMany
    {
        return $this->hasMany(Material::class, 'material_type_id');
    }
}
