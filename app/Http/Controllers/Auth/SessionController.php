<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Révocation des sessions actives (pilote base de données). Supprimer la ligne
 * de session invalide immédiatement la session côté serveur.
 */
class SessionController extends Controller
{
    public function destroy(Request $request, string $id): RedirectResponse
    {
        DB::table('sessions')
            ->where('id', $id)
            ->where('user_id', $request->user()->id)
            ->delete();

        return back()->with('status', 'Session révoquée.');
    }
}
