<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'user' => [
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'username' => $user->username,
                'grade' => $user->grade,
                'email' => $user->email,
            ],
            'sessions' => $this->sessions($request),
            'status' => session('status'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'grade' => ['nullable', 'string', 'max:100'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // Unicité par organisation (le validator n'applique pas le scope global).
                Rule::unique('users', 'email')
                    ->where('organisation_id', $user->organisation_id)
                    ->ignore($user->id),
            ],
        ]);

        $emailChanged = $validated['email'] !== $user->email;

        $user->fill([
            'first_name' => $validated['first_name'],
            'last_name' => $validated['last_name'],
            'grade' => $validated['grade'] ?? null,
            'email' => $validated['email'],
            'name' => trim($validated['first_name'].' '.$validated['last_name']),
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return back()->with('status', 'Profil mis à jour.');
    }

    /**
     * Sessions actives de l'utilisateur (pour révocation).
     *
     * @return array<int, array<string, mixed>>
     */
    private function sessions(Request $request): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        $currentId = $request->session()->getId();

        return DB::table('sessions')
            ->where('user_id', $request->user()->id)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($s) => [
                'id' => $s->id,
                'ip_address' => $s->ip_address,
                'user_agent' => $s->user_agent,
                'last_active' => Carbon::createFromTimestamp($s->last_activity)->diffForHumans(),
                'is_current' => $s->id === $currentId,
            ])
            ->all();
    }
}
