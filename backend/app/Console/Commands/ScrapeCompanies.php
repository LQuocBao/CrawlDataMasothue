<?php

namespace App\Console\Commands;

use App\Models\TelegramConfig;
use App\Services\PdfService;
use App\Services\ScraperService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Scrape DN mới + gửi Telegram + ghi Google Sheet.
 * - TẤT CẢ DN mới đều ghi vào Google Sheet
 * - Chỉ DN có SĐT mới gửi Telegram
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

        $telegramConfig = TelegramConfig::where('is_active', true)->first();
        $googleSheet = app(\App\Services\GoogleSheetService::class);
        $sentCount = 0;

        foreach ($newCompanies as $company) {
            if ($isDryRun) {
                $this->line("  [DRY-RUN] {$company->mst} - {$company->name}");
                continue;
            }

            // Ghi TẤT CẢ DN vào Google Sheet (có SĐT hay không đều ghi)
            $googleSheet->appendCompany($company);

            // Chỉ gửi Telegram cho DN có SĐT
            if (empty($company->phone) || !$telegramConfig) {
                continue;
            }

            try {
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
