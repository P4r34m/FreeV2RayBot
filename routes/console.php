<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduled tasks
|--------------------------------------------------------------------------
| Driven by the `scheduler` container (php artisan schedule:work).
*/

Schedule::command('configs:expire')->everyFiveMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('configs:sync-usage')->everyThirtyMinutes()->onOneServer()->withoutOverlapping();
Schedule::command('panels:health-check')->everyTenMinutes()->onOneServer()->withoutOverlapping();
