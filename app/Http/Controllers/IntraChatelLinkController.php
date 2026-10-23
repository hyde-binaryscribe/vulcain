<?php

namespace App\Http\Controllers;

use App\Domain\IntraChatel\Referentials;
use App\Models\ActivityLog;
use App\Models\IntraChatelRecord;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Réglages › Référentiels intra-Chatel : fiches publiées par intra-Chatel
 * (lecture seule) et leur association aux fiches Vulkain. Seule l'association
 * se modifie ici ; ni la fiche intra-Chatel ni la fiche Vulkain ne changent.
 */
class IntraChatelLinkController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function index(Request $request): Response
    {
        abort_unless(ApiTokenController::available($this->tenant), 404);
        $kinds = array_flip(Referentials::KINDS);
        $kind = in_array($request->query('kind'), Referentials::KINDS, true) ? $request->query('kind') : IntraChatelRecord::KIND_AGENT;
        $candidates = Referentials::candidates($kind);

        return Inertia::render('Settings/IntraChatel', [
            'kind' => $kind,
            'counts' => collect(Referentials::KINDS)->mapWithKeys(fn ($k) => [$k => [
                'total' => IntraChatelRecord::query()->where('kind', $k)->whereNull('missing_since')->count(),
                'unlinked' => IntraChatelRecord::query()->where('kind', $k)->whereNull('missing_since')->whereNull('linked_id')->count(),
            ]]),
            'records' => IntraChatelRecord::query()->where('kind', $kind)
                ->orderByRaw('missing_since is not null')->orderByRaw('linked_id is not null')->orderBy('label')
                ->get()
                ->map(fn (IntraChatelRecord $r) => [
                    'id' => $r->id,
                    'external_id' => $r->external_id,
                    'label' => $r->label,
                    'active' => ($r->data['active'] ?? true) !== false,
                    'present' => $r->missing_since === null,
                    'synced_at' => $r->synced_at?->toIso8601String(),
                    'linked_id' => $r->linked_id,
                    'linked_label' => $r->linked_id ? ($candidates[$r->linked_id] ?? 'Fiche Vulkain supprimée') : null,
                    'link_mode' => $r->link_mode,
                ]),
            'candidates' => $candidates->map(fn ($label, $id) => ['id' => $id, 'label' => $label])->values(),
            'segments' => $kinds,
        ]);
    }

    /** Associe (ou dissocie) une fiche intra-Chatel à une fiche Vulkain du même type. */
    public function link(Request $request, IntraChatelRecord $record): RedirectResponse
    {
        abort_unless(ApiTokenController::available($this->tenant), 404);
        $validated = $request->validate([
            'linked_id' => ['nullable', 'integer', Rule::in(Referentials::candidates($record->kind)->keys()->all())],
        ]);

        $linkedId = $validated['linked_id'] ?? null;
        $record->update(['linked_id' => $linkedId, 'link_mode' => $linkedId ? IntraChatelRecord::LINK_MANUAL : null]);

        $actor = Auth::user();
        ActivityLog::create([
            'organisation_id' => $this->tenant->id(),
            'user_id' => $actor?->getKey(),
            'actor_name' => $actor?->name ?? 'Système',
            'subject_type' => $record->getMorphClass(),
            'subject_id' => $record->id,
            'action' => $linkedId ? 'linked' : 'unlinked',
            'description' => 'Référentiel intra-Chatel « '.$record->label.' »'.($linkedId ? ' associé' : ' dissocié'),
            'properties' => null,
            'created_at' => now(),
        ]);

        return back()->with('status', $linkedId ? 'Association enregistrée.' : 'Association retirée.');
    }
}
