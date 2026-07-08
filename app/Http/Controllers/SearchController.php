<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Material;
use App\Models\Protocol;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Recherche globale : matériel, véhicules, protocoles, événements. Chaque
 * catégorie n'est incluse que si l'utilisateur a la permission correspondante.
 */
class SearchController extends Controller
{
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $user = $request->user();
        $groups = [];

        if ($q !== '') {
            $like = '%'.$q.'%';

            if ($user->can('catalog.manage')) {
                $groups[] = [
                    'label' => 'Matériel',
                    'results' => Material::query()
                        ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('reference', 'like', $like))
                        ->orderBy('name')->limit(10)->get()
                        ->map(fn (Material $m) => ['label' => $m->name, 'sub' => $m->reference, 'href' => "/materials/{$m->id}"])
                        ->values(),
                ];
            }

            if ($user->can('vehicles.manage')) {
                $groups[] = [
                    'label' => 'Véhicules',
                    'results' => Vehicle::query()
                        ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('callsign', 'like', $like)->orWhere('registration', 'like', $like))
                        ->orderBy('name')->limit(10)->get()
                        ->map(fn (Vehicle $v) => ['label' => $v->name, 'sub' => $v->callsign, 'href' => "/vehicles/{$v->id}"])
                        ->values(),
                ];
            }

            if ($user->can('protocols.perform') || $user->can('protocols.manage')) {
                $groups[] = [
                    'label' => 'Protocoles',
                    'results' => Protocol::query()
                        ->where(fn ($w) => $w->where('vehicle_name', 'like', $like)->orWhere('template_name', 'like', $like))
                        ->orderByDesc('started_at')->limit(10)->get()
                        ->map(fn (Protocol $p) => ['label' => $p->vehicle_name, 'sub' => $p->template_name, 'href' => "/protocols/{$p->id}"])
                        ->values(),
                ];
            }

            if ($user->can('anomalies.manage')) {
                $groups[] = [
                    'label' => 'Événements',
                    'results' => Event::query()
                        ->where('title', 'like', $like)
                        ->orderByDesc('created_at')->limit(10)->get()
                        ->map(fn (Event $e) => ['label' => $e->title, 'sub' => $e->type->label(), 'href' => '/events'])
                        ->values(),
                ];
            }
        }

        return Inertia::render('Search/Index', [
            'q' => $q,
            'groups' => collect($groups)->filter(fn ($g) => $g['results']->isNotEmpty())->values(),
        ]);
    }
}
