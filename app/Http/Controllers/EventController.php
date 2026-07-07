<?php

namespace App\Http\Controllers;

use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Event;
use App\Models\Material;
use App\Models\User;
use App\Models\Vehicle;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(): Response
    {
        $events = Event::query()
            ->with(['vehicle:id,name', 'material:id,name', 'assignee:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'title' => $e->title,
                'description' => $e->description,
                'status' => $e->status->value,
                'priority' => $e->priority,
                'vehicle' => $e->vehicle?->name,
                'material' => $e->material?->name,
                'assignee' => $e->assignee?->name,
                'assigned_to' => $e->assigned_to,
                'created_at' => $e->created_at?->format('d/m/Y'),
            ]);

        // Kanban : une colonne par statut, dans l'ordre.
        $columns = collect(EventStatus::ordered())->map(fn (EventStatus $s) => [
            'value' => $s->value,
            'label' => $s->label(),
            'events' => $events->where('status', $s->value)->values(),
        ]);

        return Inertia::render('Events/Index', [
            'columns' => $columns,
            'types' => EventType::options(),
            'priorities' => Event::PRIORITIES,
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'materials' => Material::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        Event::create([
            ...$validated,
            'status' => EventStatus::A_TRAITER->value,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Événement créé.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $event->update($this->validated($request));

        return back()->with('status', 'Événement mis à jour.');
    }

    /** Déplacer une carte d'une colonne à l'autre (Kanban). */
    public function move(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(EventStatus::class)],
        ]);

        $status = EventStatus::from($validated['status']);

        $event->update([
            'status' => $status->value,
            'resolved_at' => $status->isClosed() ? ($event->resolved_at ?? now()) : null,
        ]);

        return back(303)->with('status', 'Statut mis à jour.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        $event->delete();

        return back()->with('status', 'Événement supprimé.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $orgId = $this->tenant->id();

        return $request->validate([
            'type' => ['required', Rule::enum(EventType::class)],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['required', Rule::in(Event::PRIORITIES)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'material_id' => ['nullable', Rule::exists('materials', 'id')->where('organisation_id', $orgId)->whereNull('deleted_at')],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')->where('organisation_id', $orgId)],
        ]);
    }
}
