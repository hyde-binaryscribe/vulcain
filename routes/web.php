<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name', 'Vulcain'),
        'phase' => 'Phase 0 — socle en place',
    ]);
})->name('home');
