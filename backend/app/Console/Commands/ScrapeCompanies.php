<?php

namespace App\Console\Commands;

use App\Jobs\ProcessCompanyNotification;
use App\Models\TelegramConfig;
use App\Services\ScraperService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Artisan command - cron entry point chạy mỗi 1-2 phút.
 *
 * Flow:
 * 1. Scrape DN mới từ masothue.com
 * 2. Mọi DN mới đều gửi thông báo Telegram ngay lập tức (< 4 phút)
 * 3. PDF + Telegram xử lý qua Queue (async, không block scraper)
 */
class ScrapeCompanies extends Command
{
    protected $signature = 'scrape:companies
                            {--limit=50 : Số DN tối đa mỗi lần chạy}
                            {--dry-run : Chỉ xem, không lưu DB / gửi thông báo}';

    protected $description = 'Scrape DN mới từ masothue.com và gửi thông báo Telegram';

    public function __construct(
        private readonly ScraperService $scraperService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $isDryRun = $this->option('dry-run');

        $this->info('Bắt đầu scrape...');
        Log::info('ScrapeCompanies: Starting', ['dry_run' => $isDryRun]);

        // Step 1: Scrape DN mới
        $newCompanies = $this->scraperService->scrapeLatest();
        $companiesCount = count($newCompanies);

        $this->info("Tìm thấy {$companiesCount} DN mới.");

        if ($companiesCount === 0) {
            $this->info('Không có DN mới. Kết thúc.');
            return self::SUCCESS;
        }

        // Step 2: Gửi thông báo cho TẤT CẢ DN mới (không filter)
        $telegramConfig = TelegramConfig::where('is_active', true)->first();

        if (!$telegramConfig) {
            $this->warn('Chưa có cấu hình Telegram active. DN đã lưu DB nhưng không gửi thông báo.');
            return self::SUCCESS;
        }

        $dispatchCount = 0;

        foreach ($newCompanies as $company) {
            // Điều kiện tiên quyết: chỉ gửi thông báo DN có số điện thoại
            if (empty($company->phone)) {
                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY-RUN] Sẽ gửi: {$company->mst} - {$company->name}");
                continue;
            }

            // Dispatch async job: tạo PDF + gửi Telegram
            ProcessCompanyNotification::dispatchNewCompany($company, $telegramConfig);
            $dispatchCount++;
        }

        $elapsed = round(microtime(true) - $startTime, 2);

        $this->info("Hoàn tất trong {$elapsed}s. Scraped: {$companiesCount}, Jobs dispatched: {$dispatchCount}");

        Log::info('ScrapeCompanies: Done', [
            'elapsed_seconds' => $elapsed,
            'scraped' => $companiesCount,
            'dispatched' => $dispatchCount,
        ]);

        return self::SUCCESS;
    }
}
