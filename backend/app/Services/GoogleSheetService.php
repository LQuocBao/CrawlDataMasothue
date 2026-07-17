<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service ghi DN vào Google Sheet qua Apps Script webhook.
 * URL webhook được đọc từ AppSetting (cấu hình qua Dashboard).
 * Mỗi ngày 1 tab mới, tự xóa tab quá 7 ngày.
 */
class GoogleSheetService
{
    /**
     * Fallback URL nếu chưa cấu hình trong DB.
     */
    private const FALLBACK_URL = 'https://script.google.com/macros/s/AKfycbyIH3Ad1O6NbItD7C_hav21vNkc9dhl63j8AHTVFTqzBAoExtpwaYxzFh1cvl2ypop0/exec';

    /**
     * Ghi 1 DN vào Google Sheet.
     * Bao gồm cột "Nguồn" để khách phân biệt dữ liệu từ trang nào.
     */
    public function appendCompany(Company $company): bool
    {
        // Check xem Sheet có được bật không
        if (AppSetting::getValue(AppSetting::KEY_GOOGLE_SHEET_ENABLED, '1') !== '1') {
            Log::debug('GoogleSheetService: Disabled via settings, skipping.');
            return true;
        }

        $webhookUrl = $this->getWebhookUrl();

        if (!$webhookUrl) {
            Log::warning('GoogleSheetService: No webhook URL configured.');
            return false;
        }

        try {
            $primaryIndustry = '';
            $industries = $company->industries ?? [];
            $primary = collect($industries)->firstWhere('is_primary', true);
            $primaryIndustry = $primary['description'] ?? ($industries[0]['description'] ?? '');

            $response = Http::timeout(10)
                ->withOptions(['allow_redirects' => true])
                ->post($webhookUrl, [
                'mst' => $company->mst,
                'name' => $company->name,
                'phone' => $company->phone ?? '',
                'address' => $company->address ?? '',
                'representative' => $company->representative ?? '',
                'operation_date' => $company->operation_date?->format('d/m/Y') ?? '',
                'industry' => $primaryIndustry,
                'province' => $company->province ?? '',
                'source' => $company->source_label,
                'time' => now()->format('H:i:s'),
            ]);

            if ($response->successful()) {
                return true;
            }

            Log::warning('GoogleSheetService: Non-200 response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        } catch (\Throwable $e) {
            Log::error('GoogleSheetService: Failed', [
                'mst' => $company->mst,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Lấy webhook URL từ DB settings (có cache).
     */
    private function getWebhookUrl(): ?string
    {
        return AppSetting::getValue(
            AppSetting::KEY_GOOGLE_SHEET_WEBHOOK,
            self::FALLBACK_URL
        );
    }
}
