<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\Filter;
use App\Models\TelegramConfig;
use App\Services\PdfService;
use App\Services\TelegramService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job xử lý: tạo PDF + gửi Telegram cho 1 DN.
 * Chạy async trên queue, không block scraper.
 */
class ProcessCompanyNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    private Company $company;
    private ?Filter $filter;
    private ?TelegramConfig $directTelegramConfig;

    public function __construct(
        Company $company,
        Filter|TelegramConfig|null $target = null,
    ) {
        $this->company = $company;
        $this->filter = $target instanceof Filter ? $target : null;
        $this->directTelegramConfig = $target instanceof TelegramConfig ? $target : null;
        $this->onQueue('notifications');
    }

    /**
     * Static helper: dispatch cho DN mới (gửi trực tiếp, không qua filter).
     */
    public static function dispatchNewCompany(Company $company, TelegramConfig $config): void
    {
        static::dispatch($company, $config);
    }

    /**
     * Static helper: dispatch cho DN match filter.
     */
    public static function dispatchForFilter(Company $company, Filter $filter): void
    {
        static::dispatch($company, $filter);
    }

    public function handle(PdfService $pdfService, TelegramService $telegramService): void
    {
        // Xác định Telegram config
        $telegramConfig = $this->directTelegramConfig
            ?? $this->filter?->telegramConfig;

        if (!$telegramConfig || !$telegramConfig->is_active) {
            Log::warning('ProcessCompanyNotification: No active Telegram config', [
                'mst' => $this->company->mst,
            ]);
            return;
        }

        $pdfPath = null;

        try {
            // Step 1: Tạo PDF
            $pdfPath = $pdfService->generateCompanyPdf($this->company);

            Log::info('ProcessCompanyNotification: PDF generated', [
                'mst' => $this->company->mst,
            ]);

            // Step 2: Gửi Telegram
            $sent = $telegramService->sendDocument($pdfPath, $telegramConfig, $this->company);

            if ($sent) {
                $this->company->update(['notification_sent' => true]);
                Log::info('ProcessCompanyNotification: Sent OK', [
                    'mst' => $this->company->mst,
                    'chat_id' => $telegramConfig->chat_id,
                ]);
            } else {
                throw new \RuntimeException("Telegram failed for MST {$this->company->mst}");
            }
        } catch (\Throwable $e) {
            Log::error('ProcessCompanyNotification: Failed', [
                'mst' => $this->company->mst,
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        } finally {
            if ($pdfPath) {
                $pdfService->cleanup($pdfPath);
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessCompanyNotification: All retries exhausted', [
            'mst' => $this->company->mst,
            'error' => $exception->getMessage(),
        ]);
    }
}
