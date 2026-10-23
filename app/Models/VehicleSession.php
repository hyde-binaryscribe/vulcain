<?php

namespace App\Models;

use App\Domain\Webhooks\ServicePayload;
use App\Domain\Webhooks\Webhooks;
use App\Models\Concerns\BelongsToOrganisation;
use App\Models\Concerns\RecordsActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Session d'un véhicule pendant le service : ouverte par un agent après la
 * vérification de prise de service, clôturée manuellement (fin de service) ou
 * automatiquement lorsqu'un autre agent prend le véhicule (passation).
 */
class VehicleSession extends Model
{
    use BelongsToOrganisation, RecordsActivity;

    public const REASON_MANUAL = 'manual';

    public const REASON_HANDOVER = 'handover';

    /** Origine de la session : terrain Vulkain, ou application externe (lien intra-Chatel). */
    public const SOURCE_VULKAIN = 'vulkain';

    public const SOURCE_INTRA_CHATEL = 'intra_chatel';

    protected $fillable = [
        'vehicle_id',
        'source',
        'external_id',
        'close_external_id',
        'user_id',
        'opened_by_name',
        'partner_user_id',
        'partner_name',
        'opened_at',
        'open_mileage',
        'open_steps',
        'open_responses',
        'open_notes',
        'closed_at',
        'closed_by',
        'closed_by_name',
        'close_mileage',
        'close_notes',
        'close_responses',
        'close_reason',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'open_steps' => 'array',
            'open_responses' => 'array',
            'close_responses' => 'array',
            'open_mileage' => 'integer',
            'close_mileage' => 'integer',
        ];
    }

    /** Sessions encore ouvertes. */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('closed_at');
    }

    /** Sessions où l'utilisateur agit : ouvreur OU binôme (mêmes droits). */
    public function scopeForActor(Builder $query, int $userId): Builder
    {
        return $query->where(fn (Builder $q) => $q
            ->where('user_id', $userId)
            ->orWhere('partner_user_id', $userId));
    }

    /** L'utilisateur fait-il partie de l'équipage de cette session ? */
    public function involves(int $userId): bool
    {
        return $this->user_id === $userId || $this->partner_user_id === $userId;
    }

    public function isOpen(): bool
    {
        return $this->closed_at === null;
    }

    /**
     * Notifications sortantes : prise de service (création) et fin de service
     * (closed_at renseigné), quelle que soit l'origine de la session.
     */
    protected static function booted(): void
    {
        static::created(fn (self $s) => Webhooks::dispatch('service.opened', ServicePayload::of($s)));
        static::updated(function (self $s) {
            if ($s->wasChanged('closed_at') && $s->closed_at !== null) {
                Webhooks::dispatch('service.closed', ServicePayload::of($s));
            }
        });
    }

    /** Agent ayant pris le service : compte Vulkain, sinon nom saisi dans l'application d'origine. */
    public function openerName(): ?string
    {
        return $this->user?->name ?? $this->opened_by_name;
    }

    public function partnerName(): ?string
    {
        return $this->partner?->name ?? $this->partner_name;
    }

    public function closerName(): ?string
    {
        return $this->closer?->name ?? $this->closed_by_name;
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /** Agent ayant pris le service. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Binôme rattaché à la session (équipier, mêmes droits d'action). */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'partner_user_id');
    }

    /** Agent ayant clôturé (manuellement ou par passation). */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
