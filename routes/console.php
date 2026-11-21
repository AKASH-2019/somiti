<?php

use Illuminate\Support\Facades\Schedule;

// Production cron (one line): * * * * * cd /path/to/somiti && php artisan schedule:run >> /dev/null 2>&1
// Pages also roll schedules on first visit each day, so local development works without cron.
Schedule::command('somiti:roll')->dailyAt('00:05');
