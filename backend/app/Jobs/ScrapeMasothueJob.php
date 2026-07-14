<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\ScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job cào masothue.com (source gốc).
 *
 * Nằm trong Bus::chain sau RotateProxyJob.
 * Khi phát hiện DN mới → lưu DB + dispatch ProcessCompanyNotification.
 * KHÔNG render PDF hay gọi Telegram trong Job này.
 */
class ScrapeMasothueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    public function handle(ScraperService $scraper): void
    {
        Log::info('ScrapeMasothueJob: Starting');

        try {
            // ScraperService đã tự handle proxy (đọc từ TMProxy cache)
            $newCompanies = $scraper->scrapeLatest();

            if (empty($newCompanies)) {
                Log::info('ScrapeMasothueJob: No new companies.');
                return;
            }

            Log::info('ScrapeMasothueJob: Found new companies', ['count' => count($newCompanies)]);

            foreach ($newCompanies as $company) {
                // Ghi Google Sheet (TẤT CẢ DN)
                app(\App\Services\GoogleSheetService::class)->appendCompany($company);

                // Dispatch notification (PDF + Telegram) sang queue khác
                // Chỉ DN có SĐT mới dispatch
                if (!empty($company->phone)) {
                    ProcessCompanyNotification::dispatch($company)
                        ->onQueue('notifications');
                }
            }

            Log::info('ScrapeMasothueJob: Done', ['scraped' => count($newCompanies)]);
        } catch (\Throwable $e) {
            Log::error('ScrapeMasothueJob: Fatal', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
