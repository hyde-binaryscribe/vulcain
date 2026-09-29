<?php

namespace App\Http\Controllers;

use App\Domain\Catalog\MaterialStatus;
use App\Domain\Events\EventStatus;
use App\Domain\Events\EventType;
use App\Models\Event;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\Material;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\EventAssigned;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EventController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        KanbanBoard::ensureSeeded($this->tenant->organisation());

        $boards = KanbanBoard::query()->orderBy('display_order')->orderBy('id')->get(['id', 'name']);
        $currentBoardId = (int) ($request->integer('board') ?: $boards->first()?->id);
        if (! $boards->contains('id', $currentBoardId)) {
            $currentBoardId = (int) $boards->first()?->id;
        }

        $boardColumns = KanbanColumn::query()
            ->where('kanban_board_id', $currentBoardId)
            ->orderBy('display_order')->orderBy('id')
            ->get();

        $events = Event::query()
            ->whereIn('kanban_column_id', $boardColumns->pluck('id'))
            ->with(['vehicle:id,name', 'material:id,name,status', 'assignee:id,name', 'comments.author:id,name'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Event $e) => [
                'id' => $e->id,
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'title' => $e->title,
                'description' => $e->description,
                // « status » = clé de colonne (le board est générique sur les colonnes).
                'status' => (string) $e->kanban_column_id,
                'priority' => $e->priority,
                'vehicle' => $e->vehicle?->name,
                'material' => $e->material?->name,
                'material_id' => $e->material_id,
                'material_status' => $e->material?->status?->value,
                'material_status_label' => $e->material?->status?->label(),
                'assignee' => $e->assignee?->name,
                'assigned_to' => $e->assigned_to,
                'created_at' => $e->created_at?->format('d/m/Y'),
                'comments' => $e->comments->map(fn ($c) => [
                    'id' => $c->id,
                    'author' => $c->author?->name,
                    'body' => $c->body,
                    'at' => $c->created_at?->format('d/m/Y H:i'),
                ])->values(),
            ]);

        // Kanban : les colonnes configurées du tableau courant.
        $columns = $boardColumns->map(fn (KanbanColumn $c) => [
            'value' => (string) $c->id,
            'label' => $c->name,
            'is_done' => $c->is_done,
            'events' => $events->where('status', (string) $c->id)->values(),
        ]);

        return Inertia::render('Events/Index', [
            'columns' => $columns,
            'boards' => $boards,
            'current_board' => $currentBoardId,
            'can_manage_board' => $request->user()->can('anomalies.manage'),
            'types' => EventType::options(),
            'priorities' => Event::PRIORITIES,
            'vehicles' => Vehicle::query()->orderBy('name')->get(['id', 'name']),
            'materials' => Material::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'materialStatuses' => MaterialStatus::options(),
            'status' => session('status'),
        ]);
    }

    /** Ajoute un commentaire au fil de l'événement. */
    public function comment(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:2000'],
        ]);

        $event->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        return back(303)->with('status', 'Commentaire ajouté.');
    }

    /** Change le statut du matériel rattaché (volet réparation) — tracé par l'activity log. */
    public function materialStatus(Request $request, Event $event): RedirectResponse
    {
        abort_if($event->material_id === null, 404, 'Aucun matériel rattaché.');

        $validated = $request->validate([
            'status' => ['required', Rule::enum(MaterialStatus::class)],
        ]);

        // Via l'instance (et non la relation) pour déclencher l'activity log.
        $event->material->update(['status' => $validated['status']]);

        return back(303)->with('status', 'Statut du matériel mis à jour.');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validated($request);

        $event = Event::create([
            ...$validated,
            'status' => EventStatus::A_TRAITER->value,
            'kanban_column_id' => KanbanBoard::entryColumnId($this->tenant->organisation()),
            'created_by' => $request->user()->id,
        ]);

        $this->notifyAssignee($event, null, $request->user()->id);

        return back()->with('status', 'Événement créé.');
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $previous = $event->assigned_to;
        $event->update($this->validated($request));
        $this->notifyAssignee($event, $previous, $request->user()->id);

        return back()->with('status', 'Événement mis à jour.');
    }

    /** Notifie le nouvel assigné (sauf s'il s'auto-assigne ou si inchangé). */
    private function notifyAssignee(Event $event, ?int $previous, int $actorId): void
    {
        $assignee = $event->assigned_to;
        if ($assignee === null || $assignee === $previous || $assignee === $actorId) {
            return;
        }

        $event->assignee?->notify(new EventAssigned($event));
    }

    /** Déplacer une carte vers une autre colonne (Kanban). */
    public function move(Request $request, Event $event): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', 'integer'], // id de colonne
        ]);

        $column = KanbanColumn::query()->whereKey($validated['status'])->first();
        abort_if($column === null, 422, 'Colonne invalide.');

        $event->update([
            'kanban_column_id' => $column->id,
            // Statut grossier (ouvert / clos) conservé pour le tableau de bord.
            'status' => $column->is_done ? EventStatus::FERME->value : EventStatus::A_TRAITER->value,
            'resolved_at' => $column->is_done ? ($event->resolved_at ?? now()) : null,
        ]);

        return back(303)->with('status', 'Carte déplacée.');
    }

    // --- Gestion des tableaux et colonnes ---

    public function storeBoard(Request $request): RedirectResponse
    {
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $board = KanbanBoard::create([
            'name' => $validated['name'],
            'display_order' => (int) KanbanBoard::query()->max('display_order') + 1,
        ]);
        // Un tableau utilisable a au moins deux colonnes.
        $board->columns()->create(['name' => 'À faire', 'display_order' => 0, 'is_done' => false]);
        $board->columns()->create(['name' => 'Terminé', 'display_order' => 1, 'is_done' => true]);

        return redirect()->route('events.index', ['board' => $board->id])->with('status', 'Tableau créé.');
    }

    public function destroyBoard(KanbanBoard $board): RedirectResponse
    {
        abort_if(KanbanBoard::query()->count() <= 1, 422, 'Au moins un tableau est requis.');
        $hasEvents = Event::query()->whereIn('kanban_column_id', $board->columns()->pluck('id'))->exists();
        abort_if($hasEvents, 422, 'Ce tableau contient des cartes ; videz-le d’abord.');

        $board->delete();

        return redirect()->route('events.index')->with('status', 'Tableau supprimé.');
    }

    public function storeColumn(Request $request, KanbanBoard $board): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_done' => ['boolean'],
        ]);

        $board->columns()->create([
            'name' => $validated['name'],
            'is_done' => $request->boolean('is_done'),
            'display_order' => (int) $board->columns()->max('display_order') + 1,
        ]);

        return back()->with('status', 'Colonne ajoutée.');
    }

    public function updateColumn(Request $request, KanbanColumn $column): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'is_done' => ['boolean'],
        ]);

        $column->update(['name' => $validated['name'], 'is_done' => $request->boolean('is_done')]);

        return back()->with('status', 'Colonne mise à jour.');
    }

    public function destroyColumn(KanbanColumn $column): RedirectResponse
    {
        abort_if($column->events()->exists(), 422, 'Cette colonne contient des cartes ; déplacez-les d’abord.');
        abort_if($column->board->columns()->count() <= 1, 422, 'Un tableau doit garder au moins une colonne.');

        $column->delete();

        return back()->with('status', 'Colonne supprimée.');
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
