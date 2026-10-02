<?php

namespace App\Http\Controllers;

use App\Domain\Hr\FrenchHolidays;
use App\Domain\Hr\LeaveStatus;
use App\Domain\Hr\LeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveRule;
use App\Models\User;
use App\Notifications\LeaveDecided;
use App\Notifications\LeaveRequestUpdated;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    /**
     * Tableau de bord RH / Congés : présence du jour, demandes en attente et
     * soldes par période de référence (N-1 reliquat / N en cours / N+1 en
     * acquisition).
     */
    public function dashboard(): Response
    {
        $today = Carbon::today();
        [$usageStart, $usageEnd] = $this->currentLeavePeriod();

        $activeUsers = User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'job_role', 'hire_date']);
        $activeCount = $activeUsers->count();

        $onLeaveToday = LeaveRequest::query()
            ->where('status', LeaveStatus::APPROVED->value)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today)
            ->pluck('user_id')->unique();
        $onLeaveCount = $onLeaveToday->count();

        $pending = LeaveRequest::query()
            ->where('status', LeaveStatus::PENDING->value)
            ->with('user:id,name')
            ->orderBy('start_date')
            ->get()
            ->map(fn (LeaveRequest $l) => [
                'id' => $l->id,
                'user' => $l->user?->name,
                'type' => $l->type?->label(),
                'type_key' => $l->type?->value,
                'start' => $l->start_date?->format('d/m'),
                'end' => $l->end_date?->isSameDay($l->start_date) ? null : $l->end_date?->format('d/m'),
                'days' => $l->decompteDays(),
            ]);

        $balances = $activeUsers->map(function (User $u) {
            return ['id' => $u->id, 'name' => $u->name] + $this->periodBalances($u);
        });

        $totals = [
            'n_minus_1' => $balances->sum('n_minus_1'),
            'n' => $balances->sum('n'),
            'n_plus_1' => $balances->sum('n_plus_1'),
            'available' => $balances->sum('available'),
        ];

        // Soldes : reliquat N-1 le plus élevé en tête (à poser en priorité).
        $balances = $balances->sortByDesc('n_minus_1')->values();

        return Inertia::render('Hr/Dashboard', [
            'kpis' => [
                'active' => $activeCount,
                'on_leave' => $onLeaveCount,
                'pending' => $pending->count(),
                'reliquat_n1' => $totals['n_minus_1'],
                'avg_available' => $balances->isEmpty() ? 0 : (int) round($balances->avg('available')),
            ],
            'presence' => [
                'present' => max(0, $activeCount - $onLeaveCount),
                'on_leave' => $onLeaveCount,
            ],
            'pending' => $pending,
            'balances' => $balances,
            'totals' => $totals,
            'period_label' => 'mai '.$usageStart->year.' → avril '.$usageEnd->year,
            'reliquat_deadline' => $usageEnd->format('d/m/Y'),
        ]);
    }

    /**
     * Soldes de congés payés d'un agent, ventilés par période de référence :
     * N-1 (reliquat de la période précédente, à poser), N (droits de l'année
     * en cours), N+1 (en cours d'acquisition sur la période courante).
     *
     * @return array{n_minus_1:int,n:int,n_plus_1:int,available:int}
     */
    private function periodBalances(User $user): array
    {
        [$usageStart, $usageEnd] = $this->currentLeavePeriod();
        $configured = $user->job_role
            ? LeaveRule::query()->where('job_role', $user->job_role)->value('annual_days')
            : null;

        // N : période courante (mai → avril), acquisition l'année précédente.
        $entN = $this->entitlementFor($configured, $user->hire_date, $usageStart->copy()->subYear(), $usageStart->copy()->subDay());
        $remN = max(0, $entN['effective'] - $this->consumedWorkingDays($user->id, $usageStart, $usageEnd));

        // N-1 : période précédente (reliquat restant).
        $prevStart = $usageStart->copy()->subYear();
        $prevEnd = $usageEnd->copy()->subYear();
        $entPrev = $this->entitlementFor($configured, $user->hire_date, $prevStart->copy()->subYear(), $prevStart->copy()->subDay());
        $remPrev = max(0, $entPrev['effective'] - $this->consumedWorkingDays($user->id, $prevStart, $prevEnd));

        // N+1 : en cours d'acquisition sur la période courante (jusqu'à aujourd'hui).
        $now = Carbon::now();
        $acqEndNow = $now->lessThan($usageEnd) ? $now : $usageEnd;
        $entNext = $this->entitlementFor($configured, $user->hire_date, $usageStart, $acqEndNow);

        return [
            'n_minus_1' => $remPrev,
            'n' => $remN,
            'n_plus_1' => $entNext['effective'],
            'available' => $remPrev + $remN,
        ];
    }

    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->can('leave.manage');
        $profile = $this->tenant->organisation()->profile();
        $jobRoles = $profile->jobRoles();
        $roleLabels = collect($jobRoles)->pluck('label', 'value');

        $format = fn (LeaveRequest $l) => [
            'id' => $l->id,
            'user' => $l->user?->name,
            'job_role' => $l->user?->job_role,
            'type' => $l->type->value,
            'type_label' => $l->type->label(),
            'start' => $l->start_date?->toDateString(),
            'end' => $l->end_date?->toDateString(),
            'start_date' => $l->start_date?->format('d/m/Y'),
            'end_date' => $l->end_date?->format('d/m/Y'),
            'days' => $l->decompteDays(),
            'reason' => $l->reason,
            'status' => $l->status->value,
            'status_label' => $l->status->label(),
            'reviewer' => $l->reviewer?->name,
            'decision_note' => $l->decision_note,
            'modified' => $l->modified_at !== null,
            'is_owner' => $l->user_id === $user->id,
        ];

        $mine = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with('reviewer:id,name')
            ->latest('start_date')
            ->get()
            ->map($format);

        $canSubmit = $this->canSubmitForOthers($user);

        $data = [
            'mine' => $mine,
            'types' => LeaveType::options(),
            'jobRoles' => $jobRoles,
            'myBalance' => $this->balanceFor($user),
            'canManage' => $canManage,
            'canSubmitForOthers' => $canSubmit,
            'agents' => $canSubmit ? $this->submittableAgents() : [],
            'me' => ['id' => $user->id, 'name' => $user->name],
            'globalLimit' => $this->tenant->organisation()->leaveMaxSimultaneous(),
            'status' => session('status'),
        ];

        if ($canManage) {
            // Mois affiché (YYYY-MM), défaut : mois courant.
            $month = Carbon::hasFormat((string) $request->query('month'), 'Y-m')
                ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth()
                : Carbon::now()->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $rules = $this->rulesByRole();

            $calendar = LeaveRequest::query()
                ->whereIn('status', [LeaveStatus::APPROVED->value, LeaveStatus::PENDING->value])
                ->whereDate('start_date', '<=', $monthEnd)
                ->whereDate('end_date', '>=', $month)
                ->with('user:id,name,job_role')
                ->get()
                ->map($format);

            $pending = LeaveRequest::query()
                ->where('status', LeaveStatus::PENDING->value)
                ->with('user:id,name,job_role')
                ->orderBy('start_date')
                ->get()
                ->map(fn (LeaveRequest $l) => array_merge($format($l), [
                    'conflict' => $this->wouldExceed($l, $rules),
                ]));

            $data += [
                'month' => $month->format('Y-m'),
                'monthLabel' => ucfirst($month->translatedFormat('F Y')),
                'calendar' => $calendar,
                'pending' => $pending,
                'rules' => $jobRoles === [] ? [] : collect($jobRoles)->map(fn ($r) => [
                    'job_role' => $r['value'],
                    'label' => $r['label'],
                    'max_simultaneous' => $rules[$r['value']]->max_simultaneous ?? null,
                    'annual_days' => $rules[$r['value']]->annual_days ?? null,
                ])->values(),
                'team' => $this->teamBalances($roleLabels),
            ];
        }

        return Inertia::render('Leave/Index', $data);
    }

    /** Version terrain (PWA mobile) : mes congés + solde + nouvelle demande. */
    public function terrain(Request $request): Response
    {
        $user = $request->user();

        $mine = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with('reviewer:id,name')
            ->latest('start_date')
            ->get()
            ->map(fn (LeaveRequest $l) => [
                'id' => $l->id,
                'type' => $l->type->value,
                'type_label' => $l->type->label(),
                'start' => $l->start_date?->toDateString(),
                'end' => $l->end_date?->toDateString(),
                'start_date' => $l->start_date?->format('d/m/Y'),
                'end_date' => $l->end_date?->format('d/m/Y'),
                'reason' => $l->reason,
                'days' => $l->decompteDays(),
                'status' => $l->status->value,
                'status_label' => $l->status->label(),
                'reviewer' => $l->reviewer?->name,
                'decision_note' => $l->decision_note,
                'modified' => $l->modified_at !== null,
            ]);

        // Nombre de demandes à valider (pour orienter le responsable vers l'app complète).
        $toValidate = $user->can('leave.manage')
            ? LeaveRequest::query()->where('status', LeaveStatus::PENDING->value)->count()
            : 0;

        $canSubmit = $this->canSubmitForOthers($user);

        // Calendrier de charge (mois affiché, navigable via ?month=YYYY-MM).
        $month = Carbon::hasFormat((string) $request->query('month'), 'Y-m')
            ? Carbon::createFromFormat('Y-m-d', $request->query('month').'-01')->startOfMonth()
            : Carbon::now()->startOfMonth();

        return Inertia::render('Terrain/Leave', [
            'mine' => $mine,
            'types' => LeaveType::options(),
            'myBalance' => $this->balanceFor($user),
            'to_validate' => $toValidate,
            'canSubmitForOthers' => $canSubmit,
            'agents' => $canSubmit ? $this->submittableAgents() : [],
            'me' => ['id' => $user->id, 'name' => $user->name],
            'month' => $month->format('Y-m'),
            'monthLabel' => ucfirst($month->translatedFormat('F Y')),
            'calendar' => $this->leaveCalendarLevels($month),
            'globalLimit' => $this->tenant->organisation()->leaveMaxSimultaneous(),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $actor = $request->user();
        $targetId = $validated['user_id'] ?? $actor->id;

        // Déposer une demande pour un autre agent : réservé aux modérateurs
        // (et responsables). La cible doit être un agent actif de l'organisation.
        if ($targetId !== $actor->id) {
            abort_unless($this->canSubmitForOthers($actor), 403);
            $targetId = User::query()->where('is_active', true)->findOrFail($targetId)->id;
        }

        LeaveRequest::create([
            'user_id' => $targetId,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'status' => LeaveStatus::PENDING->value,
        ]);

        return back()->with('status', $targetId === $actor->id
            ? 'Demande envoyée.'
            : 'Demande envoyée pour l\'agent.');
    }

    /** Peut déposer une demande pour le compte d'un autre agent. */
    private function canSubmitForOthers(User $user): bool
    {
        return $user->can('leave.submit_for_others') || $user->can('leave.manage');
    }

    /**
     * Agents pour lesquels une demande peut être déposée (actifs de l'organisation).
     *
     * @return list<array{id:int,name:string}>
     */
    private function submittableAgents(): array
    {
        return User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name])->all();
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
        abort_unless(
            in_array($leaveRequest->status, [LeaveStatus::PENDING, LeaveStatus::APPROVED], true),
            422,
            'Cette demande ne peut plus être annulée.'
        );

        $leaveRequest->update(['status' => LeaveStatus::CANCELLED->value]);

        // L'annulation prévient obligatoirement les responsables (surtout si la
        // demande était déjà approuvée : le créneau se libère).
        $this->notifyManagers($request->user(), $leaveRequest, LeaveRequestUpdated::CANCELLED);

        return back()->with('status', 'Demande annulée. Les responsables ont été prévenus.');
    }

    /**
     * L'agent modifie sa propre demande (en attente ou approuvée) : elle repasse
     * en attente de validation, marquée « modifiée » pour éviter une validation
     * par erreur, et les responsables sont prévenus.
     */
    public function update(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
        abort_unless(
            in_array($leaveRequest->status, [LeaveStatus::PENDING, LeaveStatus::APPROVED], true),
            422,
            'Cette demande ne peut plus être modifiée.'
        );

        $validated = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $leaveRequest->update([
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            // Retour au circuit de validation : on efface la décision précédente.
            'status' => LeaveStatus::PENDING->value,
            'reviewer_id' => null,
            'decided_at' => null,
            'decision_note' => null,
            'modified_at' => now(),
        ]);

        $this->notifyManagers($request->user(), $leaveRequest, LeaveRequestUpdated::MODIFIED);

        return back()->with('status', 'Demande modifiée : elle repasse en attente de validation.');
    }

    /** Notifie les responsables (permission leave.manage), sauf l'agent lui-même. */
    private function notifyManagers(User $actor, LeaveRequest $leave, string $kind): void
    {
        $leave->loadMissing('user');

        User::query()
            ->permission('leave.manage')
            ->where('is_active', true)
            ->where('id', '!=', $actor->id)
            ->get()
            ->each(fn (User $manager) => $manager->notify(new LeaveRequestUpdated($leave, $kind)));
    }

    public function decide(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'in:approve,refuse'],
            'decision_note' => ['nullable', 'string', 'max:2000'],
        ]);

        abort_unless($leaveRequest->status === LeaveStatus::PENDING, 422, 'Cette demande a déjà été traitée.');

        $leaveRequest->update([
            'status' => $validated['action'] === 'approve' ? LeaveStatus::APPROVED->value : LeaveStatus::REFUSED->value,
            'reviewer_id' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $validated['decision_note'] ?? null,
        ]);

        $leaveRequest->user?->notify(new LeaveDecided($leaveRequest));

        return back()->with('status', 'Décision enregistrée.');
    }

    /** Enregistre les règles de congés par métier (upsert). */
    public function saveRules(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'rules' => ['present', 'array'],
            'rules.*.job_role' => ['required', 'string', 'max:50'],
            'rules.*.max_simultaneous' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rules.*.annual_days' => ['nullable', 'integer', 'min:0', 'max:366'],
            'max_simultaneous_global' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        foreach ($validated['rules'] as $rule) {
            LeaveRule::updateOrCreate(
                ['organisation_id' => $this->tenant->id(), 'job_role' => $rule['job_role']],
                ['max_simultaneous' => $rule['max_simultaneous'] ?? null, 'annual_days' => $rule['annual_days'] ?? null],
            );
        }

        // Limite globale d'absents simultanés (toutes fonctions confondues).
        $org = $this->tenant->organisation();
        $org->settings = array_merge($org->settings ?? [], [
            'leave_max_simultaneous' => $validated['max_simultaneous_global'] ?? null,
        ]);
        $org->save();

        return back()->with('status', 'Règles enregistrées.');
    }

    /** @return Collection<string, LeaveRule> */
    private function rulesByRole(): Collection
    {
        return LeaveRule::query()->get()->keyBy('job_role');
    }

    /**
     * Charge de congés jour par jour d'un mois, avec un niveau de saturation par
     * rapport aux limites (globale et par métier) : full / tight / some / none.
     *
     * @return array<string, array{count:int, level:string}>
     */
    private function leaveCalendarLevels(Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $rules = $this->rulesByRole();
        $globalLimit = $this->tenant->organisation()->leaveMaxSimultaneous();

        $leaves = LeaveRequest::query()
            ->whereIn('status', [LeaveStatus::APPROVED->value, LeaveStatus::PENDING->value])
            ->whereDate('start_date', '<=', $monthEnd)
            ->whereDate('end_date', '>=', $monthStart)
            ->with('user:id,job_role')
            ->get();

        $days = [];
        for ($day = $monthStart->copy(); $day->lte($monthEnd); $day->addDay()) {
            $onDay = $leaves->filter(fn (LeaveRequest $l) => $day->between($l->start_date, $l->end_date));
            $total = $onDay->count();
            $byRole = $onDay->groupBy(fn (LeaveRequest $l) => $l->user?->job_role)->map->count();

            $over = false;
            $tight = false;
            if ($globalLimit !== null) {
                if ($total >= $globalLimit) {
                    $over = true;
                } elseif ($total === $globalLimit - 1) {
                    $tight = true;
                }
            }
            foreach ($byRole as $role => $count) {
                $max = $role ? ($rules[$role]->max_simultaneous ?? null) : null;
                if ($max === null) {
                    continue;
                }
                if ($count >= $max) {
                    $over = true;
                } elseif ($count === $max - 1) {
                    $tight = true;
                }
            }

            $days[$day->toDateString()] = [
                'count' => $total,
                'level' => $over ? 'full' : ($tight ? 'tight' : ($total > 0 ? 'some' : 'none')),
            ];
        }

        return $days;
    }

    /**
     * Solde de congés payés « à la réalité » : 2,5 jours ouvrables acquis par
     * mois de présence sur la période de référence (mai → avril), utilisables
     * l'année suivante. Une règle métier (annual_days) peut fixer un autre total
     * annuel de référence ; à défaut, 30 jours (2,5 × 12).
     *
     * @return array{job_role:?string,annual_full:int,annual_days:int,hire_date:?string,consumed:int,remaining:int,period_label:string,monthly_rate:float,acquired_months:int}
     */
    private function balanceFor(User $user): array
    {
        [$usageStart, $usageEnd] = $this->currentLeavePeriod();
        $acqStart = $usageStart->copy()->subYear();   // 1er mai (N-1)
        $acqEnd = $usageStart->copy()->subDay();      // 30 avril (N)

        $configured = $user->job_role
            ? LeaveRule::query()->where('job_role', $user->job_role)->value('annual_days')
            : null;

        $entitlement = $this->entitlementFor($configured, $user->hire_date, $acqStart, $acqEnd);
        $consumed = $this->consumedWorkingDays($user->id, $usageStart, $usageEnd);

        return [
            'job_role' => $user->job_role,
            'annual_full' => $entitlement['full'],
            'annual_days' => $entitlement['effective'],
            'hire_date' => $user->hire_date?->format('d/m/Y'),
            'consumed' => $consumed,
            'remaining' => max(0, $entitlement['effective'] - $consumed),
            'period_label' => 'mai '.$usageStart->year.' → avril '.$usageEnd->year,
            'monthly_rate' => $entitlement['rate'],
            'acquired_months' => $entitlement['months'],
        ];
    }

    /**
     * Période de congés en cours (mai → avril), déterminée par la date du jour.
     *
     * @return array{0:Carbon,1:Carbon}
     */
    private function currentLeavePeriod(): array
    {
        $now = Carbon::now();
        $startYear = $now->month >= 5 ? $now->year : $now->year - 1;
        $start = Carbon::create($startYear, 5, 1)->startOfDay();
        $end = $start->copy()->addYear()->subDay()->endOfDay();

        return [$start, $end];
    }

    /**
     * Droits acquis sur la période de référence : 2,5 j/mois par défaut (ou
     * règle métier ÷ 12), au prorata des mois de présence, arrondi au supérieur.
     *
     * @return array{full:int,effective:int,rate:float,months:int}
     */
    private function entitlementFor(?int $configuredFull, ?Carbon $hireDate, Carbon $acqStart, Carbon $acqEnd): array
    {
        $rate = $configuredFull !== null ? $configuredFull / 12 : 2.5;
        $months = $this->monthsPresent($hireDate, $acqStart, $acqEnd);

        return [
            'full' => (int) ceil($rate * 12),
            'effective' => (int) ceil($rate * $months),
            'rate' => round($rate, 2),
            'months' => $months,
        ];
    }

    /** Nombre de mois de la période de référence (max 12) où l'agent était présent. */
    private function monthsPresent(?Carbon $hireDate, Carbon $acqStart, Carbon $acqEnd): int
    {
        if ($hireDate === null || $hireDate->lessThanOrEqualTo($acqStart)) {
            return 12;
        }
        if ($hireDate->greaterThan($acqEnd)) {
            return 0;
        }

        $months = 0;
        for ($m = $acqStart->copy()->startOfMonth(); $m->lte($acqEnd); $m->addMonth()) {
            if ($hireDate->lessThanOrEqualTo($m)) {
                $months++;
            }
        }

        return min(12, $months);
    }

    /** Jours ouvrables de congés payés approuvés consommés sur la période donnée. */
    private function consumedWorkingDays(int $userId, Carbon $periodStart, Carbon $periodEnd): int
    {
        $consuming = collect(LeaveType::cases())->filter(fn (LeaveType $t) => $t->consumesEntitlement())
            ->map(fn (LeaveType $t) => $t->value)->all();

        return (int) LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('status', LeaveStatus::APPROVED->value)
            ->whereIn('type', $consuming)
            ->whereDate('start_date', '<=', $periodEnd)
            ->whereDate('end_date', '>=', $periodStart)
            ->get()
            ->sum(function (LeaveRequest $l) use ($periodStart, $periodEnd) {
                $start = $l->start_date->greaterThan($periodStart) ? $l->start_date : $periodStart;
                $end = $l->end_date->lessThan($periodEnd) ? $l->end_date : $periodEnd;

                return FrenchHolidays::workingDaysBetween($start, $end);
            });
    }

    /**
     * Soldes de l'équipe (utilisateurs actifs ayant un métier).
     *
     * @param  Collection<string,string>  $roleLabels
     * @return list<array<string,mixed>>
     */
    private function teamBalances(Collection $roleLabels): array
    {
        [$usageStart, $usageEnd] = $this->currentLeavePeriod();
        $acqStart = $usageStart->copy()->subYear();
        $acqEnd = $usageStart->copy()->subDay();
        $rules = $this->rulesByRole();

        return User::query()->where('is_active', true)->whereNotNull('job_role')
            ->orderBy('name')->get(['id', 'name', 'job_role', 'hire_date'])
            ->map(function (User $u) use ($acqStart, $acqEnd, $usageStart, $usageEnd, $rules, $roleLabels) {
                $configured = $rules[$u->job_role]->annual_days ?? null;
                $entitlement = $this->entitlementFor($configured, $u->hire_date, $acqStart, $acqEnd);
                $consumed = $this->consumedWorkingDays($u->id, $usageStart, $usageEnd);

                return [
                    'name' => $u->name,
                    'job_role_label' => $roleLabels[$u->job_role] ?? $u->job_role,
                    'hire_date' => $u->hire_date?->format('d/m/Y'),
                    'annual_days' => $entitlement['effective'],
                    'consumed' => $consumed,
                    'remaining' => max(0, $entitlement['effective'] - $consumed),
                ];
            })->values()->all();
    }

    /**
     * L'approbation d'une demande dépasserait-elle l'effectif simultané max de
     * son métier sur au moins un jour de la période ?
     *
     * @param  Collection<string,LeaveRule>  $rules
     */
    private function wouldExceed(LeaveRequest $leave, Collection $rules): bool
    {
        $role = $leave->user?->job_role;
        $max = $role ? ($rules[$role]->max_simultaneous ?? null) : null;
        if ($max === null) {
            return false;
        }

        // Demandes approuvées du même métier qui chevauchent la période.
        $overlapping = LeaveRequest::query()
            ->where('status', LeaveStatus::APPROVED->value)
            ->whereHas('user', fn ($q) => $q->where('job_role', $role))
            ->whereDate('start_date', '<=', $leave->end_date)
            ->whereDate('end_date', '>=', $leave->start_date)
            ->get(['start_date', 'end_date']);

        // Balayage jour par jour de la période demandée.
        for ($day = $leave->start_date->copy(); $day->lte($leave->end_date); $day->addDay()) {
            $count = $overlapping->filter(fn ($o) => $day->between($o->start_date, $o->end_date))->count();
            if ($count + 1 > $max) {
                return true;
            }
        }

        return false;
    }
}
