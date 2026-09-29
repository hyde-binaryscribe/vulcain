<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Génération quotidienne des événements d'échéance (entretien, désinfection,
// péremptions, documents). Nécessite le Cron Plesk : `php artisan schedule:run`
// toutes les minutes, ou l'appel direct de cette commande une fois par jour.
Schedule::command('vulcain:generate-echeance-events')
    ->dailyAt('06:30')
    ->withoutOverlapping();
