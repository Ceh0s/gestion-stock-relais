<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Algorithme §16 : sauvegarde automatique chaque nuit. Ne tourne que si le serveur
// appelle `php artisan schedule:run` chaque minute (cron / planificateur Plesk).
Schedule::command('sauvegarde:creer')->dailyAt('02:00')->withoutOverlapping();
