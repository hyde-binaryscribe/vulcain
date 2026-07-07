<?php

namespace App\Models;

use App\Domain\Protocol\ProtocolItemState;
use App\Domain\Protocol\ProtocolSerialState;
use App\Models\Concerns\BelongsToOrganisation;
use Database\Factories\ProtocolItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProtocolItem extends Model
{
    /** @use HasFactory<ProtocolItemFactory> */
    use BelongsToOrganisation, HasFactory;

    public const MODE_QUANTITY = 'quantity';

    public const MODE_SERIAL = 'serial';

    public const MODE_LOT = 'lot';

    protected $fillable = [
        'protocol_id',
        'material_id',
        'material_name',
        'reference',
        'location_name',
        'tracking_mode',
        'expected_qty',
        'photo_required',
        'display_order',
        'serial_number',
        'last_known_expiry',
        'expiry_required',
        'observed_qty',
        'observed_expiry',
        'state',
        'observation',
        'photo_path',
        'checked',
        'row_version',
    ];

    protected function casts(): array
    {
        return [
            'last_known_expiry' => 'date',
            'observed_expiry' => 'date',
            'photo_required' => 'boolean',
            'expiry_required' => 'boolean',
            'checked' => 'boolean',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(Protocol::class);
    }

    public function isSerial(): bool
    {
        return $this->tracking_mode === self::MODE_SERIAL;
    }

    public function isLot(): bool
    {
        return $this->tracking_mode === self::MODE_LOT;
    }

    /** Libellé de l'état, selon la nature du matériel. */
    public function stateLabel(): ?string
    {
        if ($this->state === null) {
            return null;
        }

        return $this->isSerial()
            ? ProtocolSerialState::tryFrom($this->state)?->label()
            : ProtocolItemState::tryFrom($this->state)?->label();
    }

    /** L'état saisi constitue-t-il une anomalie ? */
    public function isAnomaly(): bool
    {
        if ($this->state === null) {
            return false;
        }

        return $this->isSerial()
            ? (ProtocolSerialState::tryFrom($this->state)?->isAnomaly() ?? false)
            : (ProtocolItemState::tryFrom($this->state)?->isAnomaly() ?? false);
    }
}
