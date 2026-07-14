<?php

namespace App\Jobs;

use App\Models\Company;
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
 * Job xử lý notification: PDF + Telegram.
 *
 * Chạy trên queue "notifications" — TÁCH BIỆT hoàn toàn khỏi luồng cào.
 * Nhận Company object, tự lấy TelegramConfig active để gửi.
 */
class ProcessCompanyNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function backoff(): array
    {
        return [5, 15, 30];
    }

    public function __construct(
        private readonly Company $company,
    ) {
        $this->onQueue('notifications');
    }

    public function handle(PdfService $pdfService, TelegramService $telegramService): void
    {
        // Lấy Telegram config active
        $telegramConfig = TelegramConfig::where('is_active', true)->first();

        if (!$telegramConfig) {
            Log::warning('ProcessCompanyNotification: No active Telegram config', [
                'mst' => $this->company->mst,
            ]);
            return;
        }

        // Chỉ gửi DN có SĐT
        if (empty($this->company->phone)) {
            return;
        }

        $pdfPath = null;

        try {
            // Step 1: Render PDF
            $pdfPath = $pdfService->generateCompanyPdf($this->company);

            // Step 2: Gửi Telegram
            $sent = $telegramService->sendDocument($pdfPath, $telegramConfig, $this->company);

            if ($sent) {
                $this->company->update(['notification_sent' => true]);

                Log::info('ProcessCompanyNotification: Sent OK', [
                    'mst' => $this->company->mst,
                    'chat_id' => $telegramConfig->chat_id,
                ]);
            } else {
                throw new \RuntimeException("Telegram send failed for MST {$this->company->mst}");
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
