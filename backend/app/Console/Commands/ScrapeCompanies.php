<?php

namespace App\Console\Commands;

use App\Models\TelegramConfig;
use App\Services\PdfService;
use App\Services\ScraperService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scrape DN mới + gửi Telegram trực tiếp (không qua queue).
 * Chạy mỗi 10 giây. Tất cả DN mới có SĐT được gửi ngay lập tức.
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

    public function handle(PdfService $pdfService, TelegramService $telegramService): int
    {
        $startTime = microtime(true);
        $isDryRun = $this->option('dry-run');

        $this->info('Bắt đầu scrape...');

        // Step 1: Scrape DN mới
        $newCompanies = $this->scraperService->scrapeLatest();
        $companiesCount = count($newCompanies);

        $this->info("Tìm thấy {$companiesCount} DN mới.");

        if ($companiesCount === 0) {
            $this->info('Không có DN mới. Kết thúc.');
            return self::SUCCESS;
        }

        // Step 2: Gửi Telegram TRỰC TIẾP (không qua queue)
        $telegramConfig = TelegramConfig::where('is_active', true)->first();

        if (!$telegramConfig) {
            $this->warn('Chưa có cấu hình Telegram active.');
            return self::SUCCESS;
        }

        $sentCount = 0;

        foreach ($newCompanies as $company) {
            // Chỉ gửi DN có SĐT
            if (empty($company->phone)) {
                continue;
            }

            if ($isDryRun) {
                $this->line("  [DRY-RUN] {$company->mst} - {$company->name}");
                continue;
            }

            try {
                // Tạo PDF + gửi Telegram ngay
                $pdfPath = $pdfService->generateCompanyPdf($company);
                $sent = $telegramService->sendDocument($pdfPath, $telegramConfig, $company);
                $pdfService->cleanup($pdfPath);

                if ($sent) {
                    $company->update(['notification_sent' => true]);
                    $sentCount++;
                }
            } catch (\Throwable $e) {
                Log::error("ScrapeCompanies: Failed to send {$company->mst}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $elapsed = round(microtime(true) - $startTime, 2);
        $this->info("Hoàn tất trong {$elapsed}s. Scraped: {$companiesCount}, Sent: {$sentCount}");

        return self::SUCCESS;
    }
}
