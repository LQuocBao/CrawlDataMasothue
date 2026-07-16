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
 * Khi phát hiện DN mới → lưu DB (source=masothue) + dispatch ProcessCompanyNotification.
 * Nếu DN đã tồn tại từ tramasothue → merge source thành 'both', KHÔNG gửi notification lại.
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
                // Xử lý source tracking + dedup cross-source
                $shouldNotify = $this->handleSourceTracking($company);

                // Ghi Google Sheet (TẤT CẢ DN mới — bao gồm cả không có SĐT)
                app(\App\Services\GoogleSheetService::class)->appendCompany($company);

                // Dispatch notification (PDF + Telegram) sang queue khác
                // Chỉ DN có SĐT + chưa từng gửi notification mới dispatch
                if ($shouldNotify && !empty($company->phone)) {
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

    /**
     * Xử lý source tracking cho DN vừa cào được.
     *
     * Logic Hướng C:
     * - DN hoàn toàn mới → gắn source='masothue' → return true (gửi notification)
     * - DN đã tồn tại từ nguồn khác → merge source='both', bổ sung field trống → return false (KHÔNG gửi lại)
     * - DN đã tồn tại từ cùng nguồn → skip → return false
     *
     * @return bool true nếu nên gửi notification (DN thực sự mới)
     */
    private function handleSourceTracking(Company $company): bool
    {
        // ScraperService::storeCompany() đã dùng Company::create
        // Nếu DN mới (chưa có source) → gắn source masothue
        if (!$company->source || $company->source === 'masothue') {
            // DN mới tạo bởi ScraperService → đã có default 'masothue' từ migration
            // Đảm bảo source đúng
            if ($company->source !== Company::SOURCE_MASOTHUE) {
                $company->update(['source' => Company::SOURCE_MASOTHUE]);
            }
            return true; // DN mới → gửi notification
        }

        // DN đã tồn tại từ tramasothue (hoặc both) → merge
        // Trường hợp này không xảy ra với flow hiện tại vì ScraperService::storeCompany
        // dùng Company::create (sẽ fail nếu MST đã tồn tại).
        // Nhưng để an toàn cho tương lai:
        $company->mergeSource(Company::SOURCE_MASOTHUE);
        return false; // Đã gửi notification từ nguồn trước → skip
    }
}
