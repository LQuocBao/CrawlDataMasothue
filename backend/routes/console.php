<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes (Task Scheduling)
|--------------------------------------------------------------------------
|
| The scraper runs on a tight schedule (every 1-2 minutes) to meet
| the < 4 minute latency requirement from data appearance to delivery.
|
*/

$interval = (int) config('scraper.schedule_interval', 1);

Schedule::command('scrape:companies')
    ->everyMinute()
    ->withoutOverlapping(3) // Prevent overlap, lock for 3 minutes
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scraper.log'));
