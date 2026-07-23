<?php

namespace App\Console\Commands;

use App\Jobs\RotateProxyJob;
use App\Jobs\ScrapeMasothueJob;
use App\Jobs\ScrapeTramasothueJob;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Bus;

/**
 * Command dispatch Bus::chain cho luồng cào dữ liệu.
 *
 * Chain flow:
 * 1. RotateProxyJob     → Gọi TMProxy API lấy IP mới, lưu vào cache
 * 2. ScrapeMasothueJob  → Cào masothue.com bằng IP proxy vừa lấy
 * 3. ScrapeTramasothueJob → Cào tramasothue.com.vn bằng cùng IP
 *
 * Mỗi Job Scrape khi phát hiện DN mới → dispatch ProcessCompanyNotification
 * sang queue "notifications" (tách biệt hoàn toàn).
 */
class RunScraperChain extends Command
{
    protected $signature = 'scraper:run
                            {--once : Chỉ chạy 1 lần (không loop)}';

    protected $description = 'Dispatch Bus::chain cào dữ liệu: Proxy → MaSoThue → TraMaSoThue';

    public function handle(): int
    {
        $this->info('Dispatching scraper chain...');

        Bus::chain([
            new RotateProxyJob(),
            new ScrapeMasothueJob(),
            // new ScrapeTramasothueJob(),
        ])
            ->onQueue('scraping')
            ->dispatch();

        $this->info('Chain dispatched to queue "scraping".');

        return self::SUCCESS;
    }
}
