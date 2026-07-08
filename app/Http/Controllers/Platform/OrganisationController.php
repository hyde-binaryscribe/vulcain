<?php

namespace App\Http\Controllers\Platform;

use App\Domain\Identity\OrganisationProvisioner;
use App\Domain\Sectors\Sector;
use App\Http\Controllers\Controller;
use App\Models\Organisation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganisationController extends Controller
{
    public function create(): Response
    {
        // Le provisioning d'une nouvelle organisation est réservé à l'exploitant global.
        abort_if(auth('platform')->user()->isGroupManager(), 403);

        return Inertia::render('Platform/Organisations/Create', [
            'sectors' => Sector::options(),
        ]);
    }

    public function store(Request $request, OrganisationProvisioner $provisioner): RedirectResponse
    {
        abort_if(auth('platform')->user()->isGroupManager(), 403);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'slug' => [
                'required', 'string', 'max:63',
                // Libellé de sous-domaine valide.
                'regex:/^[a-z0-9]([a-z0-9-]*[a-z0-9])?$/',
                Rule::unique('organisations', 'slug'),
            ],
            'sector' => ['required', Rule::enum(Sector::class)],
            'admin_email' => ['required', 'string', 'email', 'max:255'],
        ], [
            'slug.regex' => 'Le sous-domaine ne peut contenir que des minuscules, chiffres et tirets.',
        ]);

        $provisioner->provision([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'sector' => Sector::from($validated['sector']),
        ], $validated['admin_email']);

        return redirect()->route('platform.dashboard')->with(
            'status',
            "Organisation « {$validated['name']} » créée. Invitation envoyée à {$validated['admin_email']}."
        );
    }

    public function toggle(Organisation $organisation): RedirectResponse
    {
        abort_unless(auth('platform')->user()->canManageOrganisation($organisation), 403);

        $organisation->update([
            'status' => $organisation->isActive()
                ? Organisation::STATUS_SUSPENDED
                : Organisation::STATUS_ACTIVE,
        ]);

        return back()->with('status', "Statut de « {$organisation->name} » mis à jour.");
    }
}
