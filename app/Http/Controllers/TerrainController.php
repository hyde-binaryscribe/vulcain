<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Domain\Fleet\DisinfectionStatus;
use App\Domain\Fleet\DisinfectionType;
use App\Domain\Fleet\FuelConsumption;
use App\Domain\Fleet\MaintenanceStatus;
use App\Domain\Fleet\ProtocolFieldType;
use App\Domain\Fleet\ProtocolPhase;
use App\Domain\Fleet\VehicleDisinfection;
use App\Domain\Support\Severity;
use App\Models\BodyDamage;
use App\Models\DisinfectionProtocol;
use App\Models\DisinfectionRecord;
use App\Models\Event;
use App\Models\KanbanBoard;
use App\Models\Location;
use App\Models\Material;
use App\Models\ServiceProtocol;
use App\Models\ServiceProtocolField;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Sites\SiteScope;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Application terrain (PWA mobile) pour les salariés : accueil, fiche véhicule
 * simplifiée (checklist, désinfection, entretien), signalement d'anomalie et
 * accès par scan. Réutilise le domaine existant (véhicules, événements,
 * désinfections). Même hôte que l'app (app.vulkain.eu).
 */
class TerrainController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function home(Request $request): Response
    {
        $user = $request->user();

        // Service en cours de l'agent (ouvreur ou binôme) : fiche en avant, liste masquée.
        $myOpen = VehicleSession::query()->open()
            ->forActor($user->id)
            ->with('vehicle:id,name,callsign,type')
            ->latest('opened_at')
            ->first();
        $activeSession = $myOpen?->vehicle === null ? null : ($myOpen ? [
            'vehicle_id' => $myOpen->vehicle->id,
            'name' => $myOpen->vehicle->callsign ?: $myOpen->vehicle->name,
            'type' => $myOpen->vehicle->type,
            'opened_at' => $myOpen->opened_at?->fr('H:i'),
        ] : null);

        // Accès par QR uniquement : le personnel sans droit « véhicules » ne voit
        // pas la liste et doit scanner le QR à bord pour ouvrir une fiche.
        $qrOnly = $this->tenant->organisation()->vehicleAccessQrOnly() && ! $user->can('vehicles.manage');

        // « Mes véhicules » = véhicules affectés ; à défaut, tous ceux accessibles.
        $siteIds = SiteScope::forUser($user, session('current_site_id'));
        $assigned = $user->vehicles()->pluck('vehicles.id');

        // On masque la liste tant qu'un service est en cours (focus sur la fiche)
        // ou en mode accès par QR uniquement.
        $vehicles = ($qrOnly || $activeSession !== null) ? collect() : Vehicle::query()
            ->when($assigned->isNotEmpty(), fn ($q) => $q->whereIn('id', $assigned))
            ->when($assigned->isEmpty() && $siteIds !== null, fn ($q) => $q->whereIn('site_id', $siteIds))
            ->orderBy('name')
            ->get();

        $disinfectionStatuses = VehicleDisinfection::statusForMany($vehicles);
        $openAnomalies = Event::query()
            ->where('type', EventType::ANOMALIE->value)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->selectRaw('vehicle_id, count(*) as total')
            ->groupBy('vehicle_id')
            ->pluck('total', 'vehicle_id');

        // Sessions ouvertes (une par véhicule) : qui a pris quel véhicule.
        $openSessions = VehicleSession::query()->open()
            ->with('user:id,name')
            ->whereIn('vehicle_id', $vehicles->pluck('id'))
            ->get()
            ->keyBy('vehicle_id');

        $alertDays = $this->tenant->organisation()->maintenanceAlertDays();
        $alertKm = $this->tenant->organisation()->maintenanceAlertKm();

        $cards = $vehicles->map(function (Vehicle $v) use ($disinfectionStatuses, $openAnomalies, $openSessions, $user, $alertDays, $alertKm) {
            $disinfection = $disinfectionStatuses[$v->id] ?? DisinfectionStatus::compute(null, null);
            $maintenance = MaintenanceStatus::forVehicleRecords(
                $v->maintenances()->get(),
                $v->mileage !== null ? (int) $v->mileage : null,
                null,
                $alertDays,
                $alertKm,
            );
            $session = $openSessions->get($v->id);

            return [
                'id' => $v->id,
                'name' => $v->name,
                'callsign' => $v->callsign,
                'type' => $v->type,
                'status' => $v->status->value,
                'status_label' => $v->status->label(),
                'disinfection_severity' => $disinfection->severity?->value,
                'maintenance_severity' => $maintenance->severity?->value,
                'open_anomalies' => (int) ($openAnomalies[$v->id] ?? 0),
                'session_holder' => $session?->user?->name,
                'session_is_mine' => $session !== null && $session->involves($user->id),
            ];
        });

        return Inertia::render('Terrain/Home', [
            'greeting_name' => $user->name,
            'vehicles' => $cards,
            'totals' => [
                'anomalies' => (int) $openAnomalies->sum(),
                'disinfection_due' => $cards->whereIn('disinfection_severity', ['critical', 'warning'])->count(),
                'maintenance_due' => $cards->whereIn('maintenance_severity', ['critical', 'warning'])->count(),
            ],
            'qr_only' => $qrOnly,
            'active_session' => $activeSession,
            'can_report_anomaly' => $user->can('anomalies.manage'),
        ]);
    }

    /** Fil des événements qui impliquent l'agent (ses signalements, ceux qui lui sont assignés). */
    public function myEvents(Request $request): Response
    {
        $user = $request->user();

        $events = Event::query()
            ->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('assigned_to', $user->id))
            ->with('vehicle:id,name,callsign')
            ->latest('id')
            ->limit(80)
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'type_label' => $e->type->label(),
                'status' => $e->status->value,
                'status_label' => $e->status->label(),
                'resolved' => $e->status->isClosed(),
                'priority' => $e->priority,
                'vehicle' => $e->vehicle?->callsign ?: $e->vehicle?->name,
                'at' => $e->created_at?->fr('d/m/Y H:i'),
                'assigned' => $e->assigned_to === $user->id,
                'mine' => $e->created_by === $user->id,
            ]);

        return Inertia::render('Terrain/Events', [
            'events' => $events,
        ]);
    }

    public function vehicle(Request $request, Vehicle $vehicle, ?string $section = null): Response|RedirectResponse
    {
        $user = $request->user();
        $allowedSections = ['material', 'body', 'disinfection', 'fuel', 'maintenance', 'docs', 'mydocs', 'anomalies'];
        $section = in_array($section, $allowedSections, true) ? $section : null;
        $openSession = VehicleSession::query()->open()->with(['user:id,name', 'partner:id,name'])->where('vehicle_id', $vehicle->id)->first();
        $mine = $openSession !== null && $openSession->involves($user->id);

        // Accès conditionné à une session ouverte par l'agent (vérification de
        // prise de service obligatoire). Les gestionnaires peuvent consulter sans session.
        if (! $mine && ! $user->can('vehicles.manage')) {
            return redirect()->route('terrain.service-start', $vehicle);
        }

        $locations = Location::query()
            ->where('vehicle_id', $vehicle->id)
            ->orderBy('display_order')
            ->get();

        $materials = Material::query()
            ->whereIn('location_id', $locations->pluck('id'))
            ->with(['items', 'lots'])
            ->orderBy('name')
            ->get();

        $grouped = $locations->map(fn (Location $l) => [
            'id' => $l->id,
            'name' => $l->name,
            'materials' => $materials->where('location_id', $l->id)->map(fn (Material $m) => [
                'id' => $m->id,
                'name' => $m->name,
                'reference' => $m->reference,
                'status' => $m->status->value,
                'status_label' => $m->status->label(),
                'below_threshold' => $m->minimum_qty > 0 && $m->isBelowThreshold(),
            ])->values(),
        ]);

        $disinfections = $vehicle->disinfections()->with(['user:id,name', 'protocol:id,name'])->limit(10)->get();
        $disinfectionStatus = VehicleDisinfection::statusFor($vehicle);
        // Protocoles proposés à la saisie : ceux affectés au véhicule (sinon toute
        // la bibliothèque active, pour ne pas bloquer un enregistrement terrain).
        $assignedProtocols = $vehicle->disinfectionProtocols()->orderBy('display_order')->orderBy('name')->get();
        $protocols = ($assignedProtocols->isNotEmpty()
            ? $assignedProtocols
            : DisinfectionProtocol::query()->where('is_active', true)->orderBy('display_order')->orderBy('name')->get())
            ->map(fn (DisinfectionProtocol $p) => ['id' => $p->id, 'name' => $p->name, 'type' => $p->type->value, 'steps' => $p->steps()]);

        // Dernière désinfection par protocole (pour le détail d'échéance par protocole).
        $lastByProtocol = DisinfectionRecord::query()
            ->where('vehicle_id', $vehicle->id)
            ->whereNotNull('disinfection_protocol_id')
            ->selectRaw('disinfection_protocol_id, max(performed_at) as last_at')
            ->groupBy('disinfection_protocol_id')
            ->pluck('last_at', 'disinfection_protocol_id');

        $maintenanceStatus = MaintenanceStatus::forVehicleRecords(
            $vehicle->maintenances()->get(),
            $vehicle->mileage !== null ? (int) $vehicle->mileage : null,
            null,
            $this->tenant->organisation()->maintenanceAlertDays(),
            $this->tenant->organisation()->maintenanceAlertKm(),
        );

        $anomalies = Event::query()
            ->where('type', EventType::ANOMALIE->value)
            ->where('vehicle_id', $vehicle->id)
            ->whereIn('status', [EventStatus::A_TRAITER->value, EventStatus::EN_COURS->value])
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'title' => $e->title,
                'priority' => $e->priority,
                'created_at' => $e->created_at?->format('d/m/Y'),
                'photo_url' => $e->photo_path ? route('terrain.anomaly.photo', $e) : null,
            ]);

        $fuelEnabled = $this->tenant->organisation()->fuelTrackingEnabled();
        $fuel = $fuelEnabled ? FuelConsumption::summary($vehicle->fuelRecords()->with('user:id,name')->limit(30)->get()) : null;

        $tasks = $vehicle->tasks()->open()->with('creator:id,name')->get()
            ->map(fn ($t) => ['id' => $t->id, 'title' => $t->title, 'notes' => $t->notes, 'by' => $t->creator?->name]);

        $docMap = fn ($d) => ['id' => $d->id, 'category' => $d->category, 'title' => $d->title, 'expires_at' => $d->expires_at?->format('d/m/Y')];

        return Inertia::render('Terrain/Vehicle', [
            'section' => $section,
            'tasks' => $tasks,
            'documents' => [
                'vehicle' => $vehicle->documents()->get()->map($docMap)->values(),
                'mine' => $user->documents_consent ? $user->documents()->get()->map($docMap)->values() : [],
                'consent' => (bool) $user->documents_consent,
            ],
            'fuel' => $fuel === null ? null : [
                'last' => $fuel['last'],
                'average' => $fuel['average'],
                'total_cost' => $fuel['total_cost'],
                'records' => array_slice($fuel['records'], 0, 5),
                'can_delete' => $request->user()->can('vehicles.manage'),
            ],
            'session' => $openSession === null ? null : [
                'holder' => $openSession->user?->name,
                'is_mine' => $mine,
                'opened_at' => $openSession->opened_at?->fr('d/m/Y H:i'),
                'partner_id' => $openSession->partner_user_id,
                'partner' => $openSession->partner?->name,
                // Un équipier (ouvreur/binôme) ou un gestionnaire peut changer le binôme.
                'can_change_partner' => $mine || $user->can('vehicles.manage'),
                'crew' => ($mine || $user->can('vehicles.manage'))
                    ? $this->crewOptions($user, $openSession)
                    : [],
            ],
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'callsign' => $vehicle->callsign,
                'type' => $vehicle->type,
                'status' => $vehicle->status->value,
                'status_label' => $vehicle->status->label(),
                'mileage' => $vehicle->mileage,
            ],
            'locations' => $grouped,
            'disinfection' => [
                // « Dernière » = dernier enregistrement réel du véhicule (indépendant
                // des protocoles affectés), tandis que l'échéance/gravité vient du
                // statut agrégé par protocole.
                'last_at' => $disinfections->first()?->performed_at?->fr('d/m/Y H:i'),
                'due_at' => $disinfectionStatus->dueAt?->format('d/m/Y'),
                'severity' => $disinfectionStatus->severity?->value,
                'state_label' => $disinfectionStatus->label(),
                'types' => DisinfectionType::options(),
                'protocols' => $protocols,
                // Détail par protocole affecté (chacun sa périodicité/échéance).
                'schedules' => $assignedProtocols->map(function (DisinfectionProtocol $p) use ($lastByProtocol) {
                    $last = isset($lastByProtocol[$p->id]) ? \Illuminate\Support\Carbon::parse($lastByProtocol[$p->id]) : null;
                    $st = \App\Domain\Fleet\DisinfectionStatus::compute($last, $p->hasSchedule() ? (int) $p->frequency_days : null);

                    return [
                        'id' => $p->id,
                        'name' => $p->name,
                        'frequency_days' => $p->frequency_days,
                        'last_at' => $last?->fr('d/m/Y H:i'),
                        'due_at' => $st->dueAt?->format('d/m/Y'),
                        'severity' => $st->severity?->value,
                        'state_label' => $st->label(),
                    ];
                }),
                'can_record' => $request->user()->can('disinfections.record'),
                'records' => $disinfections->map(fn (DisinfectionRecord $d) => [
                    'type_label' => $d->type->label(),
                    'protocol' => $d->protocol?->name,
                    'performed_at' => $d->performed_at?->fr('d/m/Y H:i'),
                    'user' => $d->user?->name,
                ]),
            ],
            'maintenance' => [
                'severity' => $maintenanceStatus->severity?->value,
                'state_label' => $maintenanceStatus->label(),
                'next_due_at' => $maintenanceStatus->dueAt?->format('d/m/Y'),
                'next_due_mileage' => $maintenanceStatus->dueMileage,
                'can_update' => $request->user()->can('vehicles.manage'),
            ],
            'anomalies' => $anomalies,
            'can_report_anomaly' => $request->user()->can('anomalies.manage'),
            'body' => $this->tenant->organisation()->bodyInspectionEnabled() ? [
                'enabled' => true,
                'can_delete' => $request->user()->can('vehicles.manage'),
                'schematics' => $vehicle->vehicleModel
                    ? $vehicle->vehicleModel->schematics()->get()
                        ->mapWithKeys(fn ($s) => [$s->view => route('vehicle-models.schematic', [$vehicle->vehicle_model_id, $s->view])])->all()
                    : [],
                'damages' => $vehicle->bodyDamages()->with('reporter:id,name')->get()
                    ->map(fn (BodyDamage $d) => [
                        'id' => $d->id,
                        'view' => $d->view,
                        'pos_x' => (float) $d->pos_x,
                        'pos_y' => (float) $d->pos_y,
                        'description' => $d->description,
                        'status' => $d->status,
                        'photo_url' => $d->photo_path ? route('vehicles.body-damages.photo', [$vehicle, $d]) : null,
                        'reporter' => $d->reporter?->name,
                        'created_at' => $d->created_at?->format('d/m/Y'),
                    ])->values(),
            ] : ['enabled' => false, 'damages' => [], 'schematics' => [], 'can_delete' => false],
            'status' => session('status'),
        ]);
    }

    public function anomalyForm(Request $request): Response
    {
        return Inertia::render('Terrain/Anomaly', [
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign']),
            'vehicle_id' => $request->integer('vehicle_id') ?: null,
            'priorities' => Event::PRIORITIES,
            'status' => session('status'),
        ]);
    }

    public function reportAnomaly(Request $request): RedirectResponse
    {
        $orgId = $request->user()->organisation_id;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(Event::PRIORITIES)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'photo' => ['nullable', 'image', 'max:5120'], // 5 Mo
        ]);

        // Photo stockée sur le disque privé ; servie via une route authentifiée.
        $photoPath = $request->hasFile('photo')
            ? $request->file('photo')->store("events/{$orgId}", 'local')
            : null;

        Event::create([
            'type' => EventType::ANOMALIE->value,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'],
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'photo_path' => $photoPath,
            'status' => EventStatus::A_TRAITER->value,
            'kanban_column_id' => KanbanBoard::entryColumnId($this->tenant->organisation()),
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('terrain.home')->with('status', 'Anomalie signalée.');
    }

    /** Sert la photo d'une anomalie (disque privé, périmètre organisation via binding). */
    public function anomalyPhoto(Event $event)
    {
        abort_if($event->photo_path === null, 404);
        abort_unless(Storage::disk('local')->exists($event->photo_path), 404);

        return response()->file(Storage::disk('local')->path($event->photo_path));
    }

    public function scan(): Response
    {
        return Inertia::render('Terrain/Scan', [
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name', 'callsign']),
        ]);
    }

    /**
     * Données du contrôle carrosserie pour un véhicule (schémas du modèle +
     * anomalies connues), ou désactivé.
     *
     * @return array{enabled:bool,damages:array,schematics:array}
     */
    private function bodyInspectionData(Vehicle $vehicle): array
    {
        if (! $this->tenant->organisation()->bodyInspectionEnabled()) {
            return ['enabled' => false, 'damages' => [], 'schematics' => []];
        }

        return [
            'enabled' => true,
            'schematics' => $vehicle->vehicleModel
                ? $vehicle->vehicleModel->schematics()->get()
                    ->mapWithKeys(fn ($s) => [$s->view => route('vehicle-models.schematic', [$vehicle->vehicle_model_id, $s->view])])->all()
                : [],
            'damages' => $vehicle->bodyDamages()->get()->map(fn (BodyDamage $d) => [
                'id' => $d->id,
                'view' => $d->view,
                'pos_x' => (float) $d->pos_x,
                'pos_y' => (float) $d->pos_y,
                'description' => $d->description,
                'status' => $d->status,
            ])->values(),
        ];
    }

    /** Contrôle carrosserie obligatoire (si la fonction est activée). */
    private function validateBodyInspection(Request $request): void
    {
        if ($this->tenant->organisation()->bodyInspectionEnabled()) {
            $request->validate(
                ['body_ack' => ['accepted']],
                ['body_ack.accepted' => 'Le contrôle carrosserie est obligatoire.'],
            );
        }
    }

    /**
     * Prise de service : vérification obligatoire pour ouvrir une session.
     * Affiche le détenteur actuel s'il y en a un (l'ouverture le clôturera).
     */
    public function serviceStart(Request $request, Vehicle $vehicle): Response|RedirectResponse
    {
        $user = $request->user();
        $openSession = VehicleSession::query()->open()->with('user:id,name')->where('vehicle_id', $vehicle->id)->first();

        // Session déjà en cours pour l'agent (ouvreur ou binôme) : rien à reprendre.
        if ($openSession !== null && $openSession->involves($user->id)) {
            return redirect()->route('terrain.vehicle', $vehicle);
        }

        return Inertia::render('Terrain/ServiceStart', [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'callsign' => $vehicle->callsign,
                'type' => $vehicle->type,
                'status_label' => $vehicle->status->label(),
                'mileage' => $vehicle->mileage,
            ],
            'fields' => $this->protocolFields($vehicle, ProtocolPhase::OUVERTURE),
            'body' => $this->bodyInspectionData($vehicle),
            // Détenteur actuel : sa session sera clôturée par la passation.
            'current_holder' => $openSession?->user?->name,
            'current_since' => $openSession?->opened_at?->fr('d/m/Y H:i'),
            // Équipiers proposés pour le binôme (agents actifs de l'organisation, sauf soi).
            'crew' => $this->crewOptions($user),
            'status' => session('status'),
        ]);
    }

    /**
     * Agents actifs de l'organisation proposés comme binôme (hors utilisateur
     * courant, et hors ouvreur d'une session le cas échéant).
     *
     * @return list<array{id:int,name:string}>
     */
    private function crewOptions(User $user, ?VehicleSession $session = null): array
    {
        $exclude = array_filter([$user->id, $session?->user_id]);

        return User::query()
            ->where('is_active', true)
            ->whereNotIn('id', $exclude)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])
            ->all();
    }

    /** Ouvre une session (après vérification) ; clôture toute session en cours (passation). */
    public function openSession(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $validated = $request->validate([
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'partner_user_id' => ['nullable', 'integer'],
            'photos.*' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->validateBodyInspection($request);

        $user = $request->user();
        $partnerId = $this->resolvePartnerId($request->input('partner_user_id'), $user->id);
        $responses = $this->processResponses($request, $vehicle, ProtocolPhase::OUVERTURE);

        DB::transaction(function () use ($vehicle, $user, $validated, $responses, $partnerId) {
            // Passation : toute session ouverte du véhicule est clôturée par cette prise.
            VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->get()
                ->each(function (VehicleSession $s) use ($user) {
                    $s->update([
                        'closed_at' => now(),
                        'closed_by' => $user->id,
                        'close_reason' => VehicleSession::REASON_HANDOVER,
                        'close_notes' => "Clôturée par la prise de service de {$user->name}.",
                    ]);
                });

            VehicleSession::create([
                'vehicle_id' => $vehicle->id,
                'user_id' => $user->id,
                'partner_user_id' => $partnerId,
                'opened_at' => now(),
                'open_mileage' => $validated['mileage'] ?? null,
                'open_responses' => $responses !== [] ? $responses : null,
                'open_notes' => $validated['notes'] ?? null,
            ]);
        });

        // Le relevé met à jour le compteur du véhicule (déclenche les alertes km).
        if (! empty($validated['mileage']) && $validated['mileage'] > (int) $vehicle->mileage) {
            $vehicle->update(['mileage' => $validated['mileage']]);
        }

        return redirect()->route('terrain.vehicle', $vehicle)->with('status', 'Service ouvert.');
    }

    /** Écran de fin de service (clôture de la session en cours). */
    public function serviceEnd(Request $request, Vehicle $vehicle): Response|RedirectResponse
    {
        $session = $this->currentClosableSession($request, $vehicle);
        if ($session === null) {
            return redirect()->route('terrain.home')->with('status', 'Aucune session ouverte à clôturer.');
        }

        return Inertia::render('Terrain/ServiceEnd', [
            'vehicle' => [
                'id' => $vehicle->id,
                'name' => $vehicle->name,
                'callsign' => $vehicle->callsign,
                'mileage' => $vehicle->mileage,
            ],
            'fields' => $this->protocolFields($vehicle, ProtocolPhase::FERMETURE),
            'body' => $this->bodyInspectionData($vehicle),
            'opened_at' => $session->opened_at?->fr('d/m/Y H:i'),
        ]);
    }

    public function closeSession(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $session = $this->currentClosableSession($request, $vehicle);
        if ($session === null) {
            return redirect()->route('terrain.home')->with('error', 'Aucune session ouverte à clôturer.');
        }

        $validated = $request->validate([
            'mileage' => ['nullable', 'integer', 'min:0', 'max:9999999'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photos.*' => ['nullable', 'image', 'max:5120'],
        ]);

        $this->validateBodyInspection($request);

        $responses = $this->processResponses($request, $vehicle, ProtocolPhase::FERMETURE);

        $session->update([
            'closed_at' => now(),
            'closed_by' => $request->user()->id,
            'close_mileage' => $validated['mileage'] ?? null,
            'close_notes' => $validated['notes'] ?? null,
            'close_responses' => $responses !== [] ? $responses : null,
            'close_reason' => VehicleSession::REASON_MANUAL,
        ]);

        if (! empty($validated['mileage']) && $validated['mileage'] > (int) $vehicle->mileage) {
            $vehicle->update(['mileage' => $validated['mileage']]);
        }

        return redirect()->route('terrain.home')->with('status', 'Service clôturé.');
    }

    /**
     * Session que l'agent peut clôturer : la sienne, ou (pour un gestionnaire)
     * la session ouverte du véhicule.
     */
    private function currentClosableSession(Request $request, Vehicle $vehicle): ?VehicleSession
    {
        $user = $request->user();
        $query = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id);

        // Ouvreur comme binôme peuvent clôturer ; le gestionnaire aussi.
        if (! $user->can('vehicles.manage')) {
            $query->forActor($user->id);
        }

        return $query->first();
    }

    /**
     * Valide un identifiant de binôme : agent actif de l'organisation, différent
     * de l'ouvreur. Renvoie null si absent/invalide (le binôme est facultatif).
     */
    private function resolvePartnerId(mixed $partnerId, int $openerId): ?int
    {
        if (empty($partnerId) || (int) $partnerId === $openerId) {
            return null;
        }

        return User::query()->where('is_active', true)->whereKey((int) $partnerId)->value('id');
    }

    /** Change (ou retire) le binôme de la session ouverte d'un véhicule. */
    public function changePartner(Request $request, Vehicle $vehicle): RedirectResponse
    {
        $request->validate(['partner_user_id' => ['nullable', 'integer']]);

        $session = VehicleSession::query()->open()->where('vehicle_id', $vehicle->id)->first();
        if ($session === null) {
            return back()->with('error', 'Aucun service ouvert sur ce véhicule.');
        }

        // Seul un équipier (ouvreur/binôme) ou un gestionnaire peut modifier le binôme.
        abort_unless($session->involves($request->user()->id) || $request->user()->can('vehicles.manage'), 403);

        $session->update([
            'partner_user_id' => $this->resolvePartnerId($request->input('partner_user_id'), $session->user_id),
        ]);

        return back()->with('status', 'Binôme mis à jour.');
    }

    /**
     * Champs du protocole d'une phase pour un véhicule : protocole configuré du
     * type (ou par défaut), sinon procédure par défaut en contrôles vide/OK/NOK.
     *
     * @return list<array<string, mixed>>
     */
    private function protocolFields(Vehicle $vehicle, ProtocolPhase $phase): array
    {
        $protocol = ServiceProtocol::resolve($vehicle->type, $phase);

        if ($protocol !== null) {
            return $protocol->fields->map(fn (ServiceProtocolField $f) => [
                'key' => (string) $f->id,
                'label' => $f->label,
                'type' => $f->type->value,
                'required' => $f->required,
                'config' => $f->config ?: [],
            ])->all();
        }

        // Repli (ouverture uniquement) : procédure par défaut en contrôles vide/OK/NOK.
        if ($phase === ProtocolPhase::OUVERTURE) {
            return collect($this->tenant->organisation()->serviceStartSteps())
                ->values()
                ->map(fn ($label, $i) => ['key' => "s{$i}", 'label' => $label, 'type' => 'tristate', 'required' => false, 'config' => []])
                ->all();
        }

        return [];
    }

    /**
     * Traite les réponses soumises : stocke les photos (disque privé), construit
     * les lignes normalisées et déclenche les alertes (événements) de la phase.
     *
     * @return list<array<string, mixed>>
     */
    private function processResponses(Request $request, Vehicle $vehicle, ProtocolPhase $phase): array
    {
        $inputs = (array) $request->input('responses', []);
        $protocol = ServiceProtocol::resolve($vehicle->type, $phase);
        $rows = [];

        if ($protocol !== null) {
            foreach ($protocol->fields as $f) {
                $key = (string) $f->id;
                $value = $inputs[$key] ?? null;

                $photoPath = null;
                if ($f->type === ProtocolFieldType::PHOTO && $request->hasFile("photos.$key")) {
                    $photoPath = $request->file("photos.$key")->store("protocols/{$vehicle->organisation_id}", 'local');
                }

                $alert = $f->evaluateAlert($value);
                $rows[] = [
                    'label' => $f->label,
                    'type' => $f->type->value,
                    'value' => $f->type === ProtocolFieldType::PHOTO ? null : $value,
                    'photo_path' => $photoPath,
                    'alert' => $alert,
                ];

                if ($alert !== null) {
                    $this->createProtocolAlert($vehicle, $request->user(), $phase, $f->label, $value, $alert);
                }
            }

            return $rows;
        }

        // Repli : procédure par défaut (contrôles), pas d'alerte configurable.
        foreach ($this->tenant->organisation()->serviceStartSteps() as $i => $label) {
            $rows[] = ['label' => $label, 'type' => 'tristate', 'value' => $inputs["s{$i}"] ?? null, 'photo_path' => null, 'alert' => null];
        }

        return $rows;
    }

    /** Crée un événement (anomalie) à partir d'une alerte de protocole déclenchée. */
    private function createProtocolAlert(Vehicle $vehicle, User $user, ProtocolPhase $phase, string $label, mixed $value, array $alert): void
    {
        $priority = match ($alert['severity']) {
            Severity::CRITICAL->value => 'haute',
            Severity::WATCH->value => 'basse',
            default => 'normale',
        };

        $valueStr = match (true) {
            is_bool($value) => $value ? 'coché' : 'non coché',
            $value === 'ok' => 'OK',
            $value === 'nok' => 'NOK',
            default => trim((string) $value),
        };

        Event::create([
            'type' => EventType::ANOMALIE->value,
            'title' => '['.$phase->label().'] '.$label.($valueStr !== '' ? " : {$valueStr}" : ''),
            'description' => $alert['message'],
            'priority' => $priority,
            'status' => EventStatus::A_TRAITER->value,
            'kanban_column_id' => KanbanBoard::entryColumnId($this->tenant->organisation()),
            'vehicle_id' => $vehicle->id,
            'created_by' => $user->id,
        ]);
    }
}
