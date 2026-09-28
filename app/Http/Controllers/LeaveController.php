<?php

namespace App\Http\Controllers;

use App\Domain\Hr\LeaveStatus;
use App\Domain\Hr\LeaveType;
use App\Models\LeaveRequest;
use App\Notifications\LeaveDecided;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class LeaveController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $canManage = $user->can('leave.manage');

        $format = fn (LeaveRequest $l) => [
            'id' => $l->id,
            'user' => $l->user?->name,
            'type_label' => $l->type->label(),
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

        $pending = collect();
        if ($canManage) {
            $pending = LeaveRequest::query()
                ->where('status', LeaveStatus::PENDING->value)
                ->with('user:id,name')
                ->orderBy('start_date')
                ->get()
                ->map($format);
        }

        return Inertia::render('Leave/Index', [
            'mine' => $mine,
            'pending' => $pending,
            'types' => LeaveType::options(),
            'canManage' => $canManage,
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
}
