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
     * Pourcentage d'une jauge pour une valeur saisie, d'après min/max de la
     * config. Renvoie un entier 0–100, ou null si non calculable.
     */
    public function percentFor(mixed $value): ?int
    {
        if ($this->type !== ProtocolFieldType::GAUGE || $value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        $config = is_array($this->config) ? $this->config : [];
        if (! isset($config['min'], $config['max'])) {
            return null;
        }

        $min = (float) $config['min'];
        $max = (float) $config['max'];
        if ($max === $min) {
            return null;
        }

        $percent = (((float) $value) - $min) / ($max - $min) * 100;

        return (int) round(max(0, min(100, $percent)));
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
            // Pour une jauge, l'alerte peut porter sur la valeur brute ou sur le %.
            if ($this->type === ProtocolFieldType::GAUGE && ($alert['basis'] ?? 'value') === 'percent') {
                $percent = $this->percentFor($value);
                if ($percent === null) {
                    return null;
                }
                $v = (float) $percent;
            } else {
                $v = (float) $value;
            }
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
