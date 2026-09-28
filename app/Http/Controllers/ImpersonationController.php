<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Fin d'une session d'incarnation (support) : déconnecte l'utilisateur métier
 * incarné et renvoie l'exploitant vers le Desk.
 */
class ImpersonationController extends Controller
{
    public function stop(Request $request): RedirectResponse
    {
        $return = $request->session()->get('impersonator.return');

        Auth::guard('web')->logout();
        $request->session()->forget('impersonator');
        $request->session()->regenerate();

        return redirect()->away($return ?: '/');
    }
}
