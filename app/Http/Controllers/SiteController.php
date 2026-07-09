<?php

namespace App\Http\Controllers;

use App\Domain\Billing\PlanLimits;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    public function index(): Response
    {
        $sites = Site::query()
            ->withCount('vehicles')
            ->orderBy('name')
            ->get()
            ->map(fn (Site $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'kind' => $s->kind,
                'is_active' => $s->is_active,
                'vehicles_count' => $s->vehicles_count,
            ]);

        return Inertia::render('Sites/Index', [
            'sites' => $sites,
            'kinds' => Site::KINDS,
            'status' => session('status'),
        ]);
    }

    public function store(Request $request, PlanLimits $limits): RedirectResponse
    {
        if ($message = $limits->check('sites', Site::query()->count())) {
            return back()->with('error', $message);
        }

        Site::create($this->validated($request));

        return back()->with('status', 'Site créé.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $site->update($this->validated($request));

        return back()->with('status', 'Site mis à jour.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $site->delete();

        return back()->with('status', 'Site supprimé.');
    }

    /** Change le site actif (filtre d'affichage) — stocké en session. */
    public function switch(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_id' => ['nullable', 'integer'],
        ]);

        $id = $validated['site_id'] ?? null;

        // On n'accepte qu'un site accessible à l'utilisateur.
        $accessible = $request->user()->accessibleSiteIds();
        if ($id !== null && $accessible !== null && ! in_array($id, $accessible, true)) {
            $id = null;
        }

        if ($id === null) {
            $request->session()->forget('current_site_id');
        } else {
            $request->session()->put('current_site_id', $id);
        }

        return back(303);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'kind' => ['required', Rule::in(Site::KINDS)],
            'is_active' => ['boolean'],
        ]);

        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}
