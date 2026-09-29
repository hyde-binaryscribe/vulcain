<?php

namespace App\Models;

use App\Domain\Fleet\ProtocolFieldType;
use App\Domain\Support\Severity;
use App\Models\Concerns\BelongsToOrganisation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Champ d'un protocole de service, avec son type et un éventuel déclencheur
 * d'alerte.
 */
class ServiceProtocolField extends Model
{
    use BelongsToOrganisation;

    protected $fillable = [
        'service_protocol_id',
        'label',
        'type',
        'config',
        'required',
        'alert',
        'display_order',
    ];

    protected function casts(): array
    {
        return [
            'type' => ProtocolFieldType::class,
            'config' => 'array',
            'alert' => 'array',
            'required' => 'boolean',
        ];
    }

    public function protocol(): BelongsTo
    {
        return $this->belongsTo(ServiceProtocol::class, 'service_protocol_id');
    }

    /**
     * Évalue la réponse fournie et renvoie l'alerte déclenchée, ou null.
     *
     * @param  mixed  $value  valeur saisie (nombre, 'ok'|'nok', bool…)
     * @return array{severity:string,message:string}|null
     */
    public function evaluateAlert(mixed $value): ?array
    {
        $alert = $this->alert;
        if (! is_array($alert) || empty($alert['enabled'])) {
            return null;
        }

        $operator = $alert['operator'] ?? null;
        $triggered = false;

        if ($this->type->isNumeric()) {
            if ($value === null || $value === '' || ! is_numeric($value)) {
                return null;
            }
            $v = (float) $value;
            $t = (float) ($alert['threshold'] ?? 0);
            $triggered = match ($operator) {
                'lt' => $v < $t,
                'lte' => $v <= $t,
                'gt' => $v > $t,
                'gte' => $v >= $t,
                'eq' => $v === $t,
                default => false,
            };
        } elseif ($this->type === ProtocolFieldType::TRISTATE) {
            $triggered = $operator === 'is_nok' && $value === 'nok';
        } elseif ($this->type === ProtocolFieldType::CHECKBOX) {
            $checked = (bool) $value;
            $triggered = ($operator === 'unchecked' && ! $checked) || ($operator === 'checked' && $checked);
        }

        if (! $triggered) {
            return null;
        }

        $severity = Severity::tryFrom((string) ($alert['severity'] ?? '')) ?? Severity::WARNING;

        return [
            'severity' => $severity->value,
            'message' => trim((string) ($alert['message'] ?? '')) ?: $this->label,
        ];
    }
}
