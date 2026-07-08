<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Marque une notification comme lue puis redirige vers sa cible. */
    public function read(Request $request, string $id): RedirectResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if ($notification !== null) {
            $notification->markAsRead();

            $url = $notification->data['url'] ?? null;
            if ($url !== null) {
                return redirect($url);
            }
        }

        return back();
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back(303)->with('status', 'Notifications marquées comme lues.');
    }
}
