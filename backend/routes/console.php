<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Task Scheduling
|--------------------------------------------------------------------------
|
| Scraper chain chạy mỗi phút. Bus::chain đảm bảo:
| 1. Proxy rotate → 2. Cào masothue → 3. Cào tramasothue
| Tất cả nối đuôi nhau trên cùng 1 IP proxy.
|
| Notification (PDF + Telegram) chạy song song trên queue "notifications".
|
*/

Schedule::command('scraper:run')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/scraper.log'));
