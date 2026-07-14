<?php

namespace App\Jobs;

use App\Models\Company;
use App\Services\TraMaSoThueScraperService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Job cào tramasothue.com.vn.
 *
 * Flow:
 * 1. Fetch listing page → parse DN "Mới đăng ký"
 * 2. Lọc qua Redis dedup → chỉ giữ DN chưa từng cào
 * 3. Foreach DN mới: fetch detail → parse → lưu DB → dispatch notification
 * 4. Sleep(rand(1,3)) giữa mỗi request detail (chống WAF)
 */
class ScrapeTramasothueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 2;

    /** Cache prefix cho dedup */
    private const DEDUP_PREFIX = 'scraped_tramasothue:';
    private const DEDUP_TTL = 2592000; // 30 ngày

    public function handle(TraMaSoThueScraperService $scraper): void
    {
        $proxy = RotateProxyJob::getCurrentProxy();

        Log::info('ScrapeTramasothueJob: Starting', [
            'proxy' => $proxy ? 'yes' : 'direct',
        ]);

        try {
            // Step 1: Fetch listing page
            $newCompanyUrls = $scraper->fetchNewCompanyUrls($proxy);

            if (empty($newCompanyUrls)) {
                Log::info('ScrapeTramasothueJob: No new companies found.');
                return;
            }

            Log::info('ScrapeTramasothueJob: Found URLs', ['count' => count($newCompanyUrls)]);

            // Step 2: Lọc qua Redis dedup
            $newUrls = $this->filterDuplicates($newCompanyUrls);

            if (empty($newUrls)) {
                Log::info('ScrapeTramasothueJob: All URLs already processed.');
                return;
            }

            Log::info('ScrapeTramasothueJob: New URLs to scrape', ['count' => count($newUrls)]);

            // Step 3: Foreach DN mới → fetch detail → lưu DB → dispatch
            $scraped = 0;

            foreach ($newUrls as $url) {
                try {
                    $companyData = $scraper->scrapeCompanyDetail($url, $proxy);

                    if (!$companyData || empty($companyData['mst'])) {
                        continue;
                    }

                    // Lưu vào MySQL
                    $company = Company::updateOrCreate(
                        ['mst' => $companyData['mst']],
                        array_merge($companyData, ['scraped_at' => now()])
                    );

                    // Đánh dấu dedup
                    $this->markAsProcessed($companyData['mst']);

                    // Dispatch notification sang queue khác (PDF + Telegram)
                    ProcessCompanyNotification::dispatch($company)
                        ->onQueue('notifications');

                    // Ghi Google Sheet (tất cả DN)
                    app(\App\Services\GoogleSheetService::class)->appendCompany($company);

                    $scraped++;
                } catch (\Throwable $e) {
                    Log::warning('ScrapeTramasothueJob: Failed to scrape URL', [
                        'url' => $url,
                        'error' => $e->getMessage(),
                    ]);
                }

                // BẮT BUỘC: sleep random 1-3 giây giữa mỗi request (chống WAF)
                sleep(rand(1, 3));
            }

            Log::info('ScrapeTramasothueJob: Done', ['scraped' => $scraped]);
        } catch (\Throwable $e) {
            Log::error('ScrapeTramasothueJob: Fatal error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Lọc URLs đã xử lý (Redis dedup).
     */
    private function filterDuplicates(array $urls): array
    {
        return array_filter($urls, function (string $url) {
            // Extract MST từ URL: /0402347844-cong-ty-...
            if (preg_match('/\/(\d{10,14})-/', $url, $m)) {
                $mst = $m[1];
                if (Cache::has(self::DEDUP_PREFIX . $mst)) {
                    return false;
                }
                // Cũng check DB
                if (Company::where('mst', $mst)->exists()) {
                    $this->markAsProcessed($mst);
                    return false;
                }
                return true;
            }
            return false;
        });
    }

    /**
     * Đánh dấu MST đã xử lý.
     */
    private function markAsProcessed(string $mst): void
    {
        Cache::put(self::DEDUP_PREFIX . $mst, true, self::DEDUP_TTL);
    }
}
