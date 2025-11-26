<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Envia notificaciones a las 8am y 2pm
Schedule::command('notificaciones:revisar-pendientes')->twiceDaily(8, 14);