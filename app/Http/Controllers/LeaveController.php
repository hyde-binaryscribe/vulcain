<?php

namespace App\Http\Controllers;

use App\Domain\Hr\LeaveStatus;
use App\Domain\Hr\LeaveType;
use App\Models\LeaveRequest;
use App\Models\LeaveRule;
use App\Models\User;
use App\Notifications\LeaveDecided;
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
            'days' => $l->days(),
            'reason' => $l->reason,
            'status' => $l->status->value,
            'status_label' => $l->status->label(),
            'reviewer' => $l->reviewer?->name,
            'decision_note' => $l->decision_note,
            'is_owner' => $l->user_id === $user->id,
        ];

        $mine = LeaveRequest::query()
            ->where('user_id', $user->id)
            ->with('reviewer:id,name')
            ->latest('start_date')
            ->get()
            ->map($format);

        $data = [
            'mine' => $mine,
            'types' => LeaveType::options(),
            'jobRoles' => $jobRoles,
            'myBalance' => $this->balanceFor($user),
            'canManage' => $canManage,
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

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', Rule::enum(LeaveType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        LeaveRequest::create([
            'user_id' => $request->user()->id,
            'type' => $validated['type'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'],
            'reason' => $validated['reason'] ?? null,
            'status' => LeaveStatus::PENDING->value,
        ]);

        return back()->with('status', 'Demande envoyée.');
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($leaveRequest->user_id === $request->user()->id, 403);
        abort_unless($leaveRequest->status === LeaveStatus::PENDING, 422, 'Seule une demande en attente peut être annulée.');

        $leaveRequest->update(['status' => LeaveStatus::CANCELLED->value]);

        return back()->with('status', 'Demande annulée.');
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
            'rules' => ['required', 'array'],
            'rules.*.job_role' => ['required', 'string', 'max:50'],
            'rules.*.max_simultaneous' => ['nullable', 'integer', 'min:0', 'max:999'],
            'rules.*.annual_days' => ['nullable', 'integer', 'min:0', 'max:366'],
        ]);

        foreach ($validated['rules'] as $rule) {
            LeaveRule::updateOrCreate(
                ['organisation_id' => $this->tenant->id(), 'job_role' => $rule['job_role']],
                ['max_simultaneous' => $rule['max_simultaneous'] ?? null, 'annual_days' => $rule['annual_days'] ?? null],
            );
        }

        return back()->with('status', 'Règles enregistrées.');
    }

    /** @return Collection<string, LeaveRule> */
    private function rulesByRole(): Collection
    {
        return LeaveRule::query()->get()->keyBy('job_role');
    }

    /**
     * Solde annuel d'un utilisateur : droits (selon la règle de son métier),
     * jours consommés (congés payés approuvés de l'année) et restant.
     *
     * @return array{job_role:?string,annual_days:?int,consumed:int,remaining:?int}
     */
    private function balanceFor(User $user): array
    {
        $year = Carbon::now()->year;
        $annual = $user->job_role
            ? LeaveRule::query()->where('job_role', $user->job_role)->value('annual_days')
            : null;

        $consumed = $this->consumedDays($user->id, $year);

        return [
            'job_role' => $user->job_role,
            'annual_days' => $annual,
            'consumed' => $consumed,
            'remaining' => $annual !== null ? max(0, $annual - $consumed) : null,
        ];
    }

    /** Jours de congés payés approuvés consommés par un utilisateur sur une année. */
    private function consumedDays(int $userId, int $year): int
    {
        $yearStart = Carbon::create($year, 1, 1)->startOfDay();
        $yearEnd = Carbon::create($year, 12, 31)->endOfDay();

        $consuming = collect(LeaveType::cases())->filter(fn (LeaveType $t) => $t->consumesEntitlement())
            ->map(fn (LeaveType $t) => $t->value)->all();

        return (int) LeaveRequest::query()
            ->where('user_id', $userId)
            ->where('status', LeaveStatus::APPROVED->value)
            ->whereIn('type', $consuming)
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->get()
            ->sum(function (LeaveRequest $l) use ($yearStart, $yearEnd) {
                $start = $l->start_date->greaterThan($yearStart) ? $l->start_date : $yearStart;
                $end = $l->end_date->lessThan($yearEnd) ? $l->end_date : $yearEnd;

                return $start->diffInDays($end) + 1;
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
        $year = Carbon::now()->year;
        $rules = $this->rulesByRole();

        return User::query()->where('is_active', true)->whereNotNull('job_role')
            ->orderBy('name')->get(['id', 'name', 'job_role'])
            ->map(function (User $u) use ($year, $rules, $roleLabels) {
                $annual = $rules[$u->job_role]->annual_days ?? null;
                $consumed = $this->consumedDays($u->id, $year);

                return [
                    'name' => $u->name,
                    'job_role_label' => $roleLabels[$u->job_role] ?? $u->job_role,
                    'annual_days' => $annual,
                    'consumed' => $consumed,
                    'remaining' => $annual !== null ? max(0, $annual - $consumed) : null,
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
