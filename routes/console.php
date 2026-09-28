<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('calls:expire')->everyMinute()->withoutOverlapping();
Schedule::command('class-announcements:process')->everyMinute()->withoutOverlapping();
Schedule::command('class-meetings:replenish')->daily()->withoutOverlapping();
