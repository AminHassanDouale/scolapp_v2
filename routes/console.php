<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Dispatch scheduled tasks every minute so tasks fire at their configured time
Schedule::command('tasks:dispatch')->everyMinute()->withoutOverlapping();
