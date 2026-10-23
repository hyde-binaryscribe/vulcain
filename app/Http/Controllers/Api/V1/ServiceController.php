<?php

namespace App\Http\Controllers\Api\V1;

use App\Domain\IntraChatel\ServiceChecklist;
use App\Domain\Webhooks\ServicePayload;
use App\Http\Controllers\Controller;
use App\Models\OxygenBottle;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * API v1 — prises et fins de service (lien intra-Chatel, docs/LIEN-INTRA-CHATEL.md).
 *
 * Lecture : services récents (polling) et service en cours d'un véhicule (de quoi
 * préremplir le formulaire). Écriture : intra-Chatel envoie ses formulaires de prise
 * de poste et de fin de service ; ils deviennent des sessions Vulkain, comme celles du
 * terrain. Chaque envoi porte un identifiant externe : un renvoi ne crée rien de plus.
 */
class ServiceController extends Controller
{
    /** Services modifiés depuis une date (par défaut 24 h), du plus ancien au plus récent. */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->query(), [
            'since' => ['nullable', 'date'],
            'vehicle_uuid' => ['nullable', 'uuid'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        $since = $request->query('since') ? CarbonImmutable::parse($request->query('since')) : now()->subDay();
        $query = VehicleSession::query()->where('updated_at', '>=', $since)->orderBy('updated_at')->orderBy('id');
        if ($request->query('vehicle_uuid')) {
            $query->whereIn('vehicle_id', Vehicle::query()->where('uuid', $request->query('vehicle_uuid'))->select('id'));
        }

        $sessions = $query->limit((int) ($request->query('limit') ?? 100))->get();

        return response()->json(['data' => $sessions->map(fn (VehicleSession $s) => ServicePayload::of($s))->values()]);
    }

    /**
     * Service en cours d'un véhicule, son compteur et les dernières bouteilles
     * d'oxygène relevées : bandeau « Session de … non clôturée », rappel
     * « équipage — km » et préremplissage des n° de série.
     */
    public function current(Request $request): JsonResponse
    {
        [$vehicle, $error] = $this->resolveVehicle($request->query());
        if ($error) {
            return $error;
        }

        $open = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->latest('opened_at')->first();
        $last = $open ?? VehicleSession::query()->where('vehicle_id', $vehicle->id)->latest('opened_at')->latest('id')->first();

        return response()->json(['data' => [
            'vehicle' => [
                'uuid' => $vehicle->uuid,
                'callsign' => $vehicle->callsign,
                'name' => $vehicle->name,
                'mileage' => $vehicle->mileage,
                'mileage_at' => $vehicle->mileage_at?->toIso8601String(),
            ],
            'open_service' => $open ? ServicePayload::of($open) : null,
            'last_oxygen' => $this->lastOxygen($vehicle, $last),
        ]]);
    }

    /** Prise de poste envoyée par intra-Chatel. Clôture la session en cours du véhicule (passation). */
    public function open(Request $request): JsonResponse
    {
        $input = $request->json()->all();
        $validator = Validator::make($input, [
            'external_id' => ['required', 'string', 'max:100'],
            'vehicle_uuid' => ['nullable', 'uuid'],
            'vehicle_callsign' => ['nullable', 'string', 'max:50'],
            'opened_by' => ['required', 'string', 'max:100'],
            'partner' => ['nullable', 'string', 'max:100'],
            'opened_at' => ['nullable', 'date'],
        ] + ServiceChecklist::openRules());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        $existing = VehicleSession::query()->where('external_id', $input['external_id'])->first();
        if ($existing) {
            return $this->replayed($existing);
        }

        [$vehicle, $error] = $this->resolveVehicle($input);
        if ($error) {
            return $error;
        }

        $openedBy = trim($input['opened_by']);
        $partner = isset($input['partner']) && trim($input['partner']) !== '' ? trim($input['partner']) : null;
        $openedAt = $this->moment($input['opened_at'] ?? null);
        $openerId = $this->userIdFor($openedBy);
        $handover = null;

        try {
            $session = DB::transaction(function () use ($input, $vehicle, $openedBy, $partner, $openedAt, $openerId, &$handover) {
                VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->get()
                    ->each(function (VehicleSession $s) use ($openedBy, $openedAt, $openerId, &$handover) {
                        $handover = ['opened_by' => $s->openerName(), 'opened_at' => $s->opened_at?->toIso8601String()];
                        $s->update([
                            'closed_at' => $openedAt->max($s->opened_at),
                            'closed_by' => $openerId,
                            'closed_by_name' => $openedBy,
                            'close_reason' => VehicleSession::REASON_HANDOVER,
                            'close_notes' => "Clôturée par la prise de poste de {$openedBy} (intra-Chatel).",
                        ]);
                    });

                $rows = ServiceChecklist::openRows($input);

                return VehicleSession::create([
                    'vehicle_id' => $vehicle->id,
                    'source' => VehicleSession::SOURCE_INTRA_CHATEL,
                    'external_id' => $input['external_id'],
                    'user_id' => $openerId,
                    'opened_by_name' => $openedBy,
                    'partner_user_id' => $partner ? $this->userIdFor($partner, $openerId) : null,
                    'partner_name' => $partner,
                    'opened_at' => $openedAt,
                    'open_mileage' => $input['mileage'] ?? null,
                    'open_responses' => $rows,
                    'open_notes' => ServiceChecklist::notes($input),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés du même formulaire : le premier l'emporte.
            return $this->replayed(VehicleSession::query()->where('external_id', $input['external_id'])->firstOrFail());
        }

        $vehicle->recordMileage(isset($input['mileage']) ? (int) $input['mileage'] : null, 'prise_de_service', $openedBy);

        return response()->json(['data' => ServicePayload::of($session->fresh()), 'handover' => $handover, 'replayed' => false], 201);
    }

    /** Fin de service envoyée par intra-Chatel : clôture la session en cours du véhicule. */
    public function close(Request $request): JsonResponse
    {
        $input = $request->json()->all();
        $validator = Validator::make($input, [
            'external_id' => ['required', 'string', 'max:100'],
            'vehicle_uuid' => ['nullable', 'uuid'],
            'vehicle_callsign' => ['nullable', 'string', 'max:50'],
            'closed_by' => ['required', 'string', 'max:100'],
            'closed_at' => ['nullable', 'date'],
        ] + ServiceChecklist::sharedRules());
        if ($validator->fails()) {
            return $this->invalid($validator->errors()->toArray());
        }

        $existing = VehicleSession::query()->where('close_external_id', $input['external_id'])->first();
        if ($existing) {
            return $this->replayed($existing);
        }

        [$vehicle, $error] = $this->resolveVehicle($input);
        if ($error) {
            return $error;
        }

        $session = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->latest('opened_at')->first();
        if (! $session) {
            return response()->json(['error' => 'Aucun service en cours pour ce véhicule.', 'code' => 'no_open_service'], 409);
        }

        $closedBy = trim($input['closed_by']);

        try {
            $session->update([
                'close_external_id' => $input['external_id'],
                'closed_at' => $this->moment($input['closed_at'] ?? null)->max($session->opened_at),
                'closed_by' => $this->userIdFor($closedBy),
                'closed_by_name' => $closedBy,
                'close_mileage' => $input['mileage'] ?? null,
                'close_responses' => ServiceChecklist::closeRows($input),
                'close_notes' => ServiceChecklist::notes($input),
                'close_reason' => VehicleSession::REASON_MANUAL,
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->replayed(VehicleSession::query()->where('close_external_id', $input['external_id'])->firstOrFail());
        }

        $vehicle->recordMileage(isset($input['mileage']) ? (int) $input['mileage'] : null, 'fin_de_service', $closedBy);

        return response()->json(['data' => ServicePayload::of($session->fresh()), 'replayed' => false]);
    }

    /**
     * Véhicule désigné par son uuid (de préférence) ou son indicatif (« A9 »).
     *
     * @return array{0: ?Vehicle, 1: ?JsonResponse}
     */
    private function resolveVehicle(array $input): array
    {
        $uuid = $input['vehicle_uuid'] ?? null;
        $callsign = isset($input['vehicle_callsign']) ? trim((string) $input['vehicle_callsign']) : '';

        if ($uuid) {
            $vehicle = Vehicle::query()->where('uuid', $uuid)->first();
        } elseif ($callsign !== '') {
            $matches = Vehicle::query()->whereRaw('lower(callsign) = ?', [mb_strtolower($callsign)])->limit(2)->get();
            if ($matches->count() > 1) {
                return [null, response()->json(['error' => "Plusieurs véhicules portent l'indicatif « {$callsign} » : utilisez vehicle_uuid.", 'code' => 'ambiguous_vehicle'], 422)];
            }
            $vehicle = $matches->first();
        } else {
            return [null, $this->invalid(['vehicle_uuid' => ['vehicle_uuid ou vehicle_callsign est obligatoire.']])];
        }

        if (! $vehicle) {
            return [null, response()->json(['error' => 'Véhicule introuvable.', 'code' => 'vehicle_not_found'], 404)];
        }

        return [$vehicle, null];
    }

    /**
     * Agent Vulkain correspondant aux initiales saisies dans intra-Chatel : celui dont
     * l'identifiant interne (username) est identique, s'il est seul. Sinon null :
     * le nom saisi reste affiché tel quel.
     */
    private function userIdFor(string $name, ?int $except = null): ?int
    {
        $ids = User::query()->where('is_active', true)
            ->whereRaw('lower(username) = ?', [mb_strtolower($name)])
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->limit(2)->pluck('id');

        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    /** Heure saisie dans intra-Chatel, jamais dans le futur ; maintenant si absente. */
    private function moment(?string $value): CarbonImmutable
    {
        $now = CarbonImmutable::now();

        return $value ? CarbonImmutable::parse($value)->min($now) : $now;
    }

    /**
     * Dernières bouteilles relevées par intra-Chatel (fin de service si présente, sinon
     * prise de poste) ; à défaut, les bouteilles de la dotation Vulkain (Oxygène embarqué).
     */
    private function lastOxygen(Vehicle $vehicle, ?VehicleSession $s): array
    {
        $rows = collect($s ? ($s->close_responses ?: $s->open_responses ?: []) : []);

        $readings = $rows->filter(fn ($r) => preg_match('/^oxygen\.\d+\.serial_number$/', (string) ($r['ref'] ?? '')))
            ->map(function ($r) use ($rows) {
                $n = (int) explode('.', $r['ref'])[1];
                $level = $rows->firstWhere('ref', "oxygen.{$n}.level_percent");

                return ['position' => $n, 'serial_number' => $r['value'] ?? null, 'level_percent' => $level['value'] ?? null, 'source' => 'service'];
            })->values();
        if ($readings->isNotEmpty()) {
            return $readings->all();
        }

        return OxygenBottle::query()->where('vehicle_id', $vehicle->id)->where('is_active', true)
            ->orderBy('display_order')->orderBy('id')->limit(ServiceChecklist::MAX_BOTTLES)->get()
            ->values()
            ->map(fn (OxygenBottle $b, int $i) => [
                'position' => $i + 1, 'serial_number' => $b->serial_number, 'level_percent' => $b->percent(), 'source' => 'dotation',
            ])->all();
    }

    private function replayed(VehicleSession $s): JsonResponse
    {
        return response()->json(['data' => ServicePayload::of($s), 'replayed' => true]);
    }

    private function invalid(array $errors): JsonResponse
    {
        return response()->json(['error' => 'Données invalides.', 'code' => 'validation_failed', 'errors' => $errors], 422);
    }
}
