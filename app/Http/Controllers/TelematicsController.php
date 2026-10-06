<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Event;
use App\Models\KanbanBoard;
use App\Models\Organisation;
use App\Models\Vehicle;
use App\Models\VehiclePosition;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

/**
 * Ingestion des positions télématiques transférées par Traccar (en bordure,
 * sur le VPS). Webhook public protégé par un jeton dans l'URL — Traccar ne peut
 * pas s'authentifier, et l'hébergement applicatif reste chrooté (pas de port
 * TCP à exposer : Traccar fait le décodage, Vulkain ne fait que recevoir).
 */
class TelematicsController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function ingest(Request $request, ?string $token = null): Response
    {
        $this->authorizeToken($request, $token);

        $data = $request->json()->all() ?: $request->all();
        $device = $data['device'] ?? [];
        $position = $data['position'] ?? $data;

        $imei = trim((string) ($device['uniqueId'] ?? $data['uniqueId'] ?? $data['imei'] ?? ($position['uniqueId'] ?? '')));
        $lat = $position['latitude'] ?? $position['lat'] ?? null;
        $lon = $position['longitude'] ?? $position['lon'] ?? null;

        // Rien d'exploitable : on acquitte quand même pour ne pas faire boucler Traccar.
        if ($imei === '' || $lat === null || $lon === null) {
            return $this->ack('ignored');
        }

        // Résolution IMEI → véhicule (cross-tenant : le webhook n'a pas de contexte).
        $vehicle = $this->tenant->runCrossTenant(
            fn () => Vehicle::query()->where('telematics_imei', $imei)->first()
        );
        if ($vehicle === null) {
            return $this->ack('unknown-device');
        }

        $organisation = $this->tenant->runCrossTenant(fn () => Organisation::query()->find($vehicle->organisation_id));
        if ($organisation === null) {
            return $this->ack('unknown-org');
        }

        $attributes = (array) ($position['attributes'] ?? []);

        $this->tenant->runFor($organisation, function () use ($vehicle, $position, $lat, $lon, $attributes) {
            VehiclePosition::create([
                'vehicle_id' => $vehicle->id,
                'latitude' => (float) $lat,
                'longitude' => (float) $lon,
                // Traccar exprime la vitesse en nœuds ; on stocke en km/h.
                'speed' => isset($position['speed']) ? (int) round((float) $position['speed'] * 1.852) : null,
                'course' => isset($position['course']) ? (int) round((float) $position['course']) : null,
                'altitude' => isset($position['altitude']) ? (int) round((float) $position['altitude']) : null,
                'valid' => (bool) ($position['valid'] ?? true),
                'attrs' => $attributes ?: null,
                'device_time' => $this->parseTime($position['deviceTime'] ?? $position['fixTime'] ?? null),
                'server_time' => $this->parseTime($position['serverTime'] ?? null) ?? Carbon::now(),
            ]);

            $this->handleDtc($vehicle, $attributes);
        });

        return $this->ack('stored');
    }

    /**
     * Codes défaut OBD remontés par le boîtier : crée/maintient un événement
     * tant qu'il y a des codes, le clôt quand il n'y en a plus (déduplication
     * par source_key, une alerte ouverte par véhicule).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function handleDtc(Vehicle $vehicle, array $attributes): void
    {
        $faultCount = (int) ($attributes['faultCount'] ?? 0);
        $dtcs = trim((string) ($attributes['dtcs'] ?? ''));
        $sourceKey = 'telematics-dtc:'.$vehicle->id;

        if ($faultCount <= 0 && $dtcs === '') {
            Event::query()->where('source_key', $sourceKey)
                ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
                ->update(['status' => EventStatus::RESOLU->value, 'resolved_at' => Carbon::now()]);

            return;
        }

        $description = 'Codes défaut remontés par le boîtier'
            .($dtcs !== '' ? ' : '.$dtcs : '')
            .' ('.max($faultCount, 1).').';

        $open = Event::query()->where('source_key', $sourceKey)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->first();

        if ($open !== null) {
            $open->update(['description' => $description]);

            return;
        }

        Event::create([
            'type' => EventType::ANOMALIE->value,
            'source_key' => $sourceKey,
            'title' => "Défaut moteur (OBD) — {$vehicle->name}",
            'description' => $description,
            'priority' => 'normale',
            'status' => EventStatus::A_TRAITER->value,
            'kanban_column_id' => KanbanBoard::entryColumnId($this->tenant->organisation()),
            'vehicle_id' => $vehicle->id,
            'created_by' => null,
        ]);
    }

    private function parseTime(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }
        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Acquittement HTTP léger (toujours 200 pour éviter les ré-émissions). */
    private function ack(string $status): Response
    {
        return response("{$status}\n", 200)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    /** Jeton exigé (chemin, query ?token= ou en-tête). 404 si non configuré. */
    private function authorizeToken(Request $request, ?string $routeToken): void
    {
        $expected = (string) config('security.telematics.token', '');
        $provided = (string) ($routeToken
            ?? $request->query('token')
            ?? $request->header('X-Telematics-Token', ''));

        abort_if($expected === '' || ! hash_equals($expected, $provided), 404);
    }
}
