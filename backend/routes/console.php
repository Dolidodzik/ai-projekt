<?php

// Definicje komend konsolowych i harmonogramu Laravela (scheduler).

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Codziennie o północy sprawdza, czy jest nowszy feed GTFS i ewentualnie go importuje.
Schedule::command('gtfs:sync')->dailyAt('00:00');
