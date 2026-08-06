<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily database snapshot — see docs/15-database-backups.md.
// Host still needs `* * * * * php artisan schedule:run` in cron.
Schedule::exec(base_path('scripts/db-backup.sh'))
    ->dailyAt('02:30')
    ->name('database-backup')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/backup.log'));
