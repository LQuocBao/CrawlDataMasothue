<?php

namespace App\Services;

use App\Models\Company;
use App\Models\TelegramConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Service for sending notifications (documents, messages) via Telegram Bot API.
 */
class TelegramService
{
    private const API_BASE = 'https://api.telegram.org/bot';

    /**
     * Send a PDF document to a Telegram chat with a formatted caption.
     *
     * @param string $filePath Absolute path to the PDF file
     * @param TelegramConfig $config Telegram bot + chat configuration
     * @param Company $company Company data for the caption
     * @return bool Whether the message was sent successfully
     */
    public function sendDocument(
        string $filePath,
        TelegramConfig $config,
        Company $company
    ): bool {
        $url = self::API_BASE . $config->bot_token . '/sendDocument';

        $caption = $this->buildCaption($company);

        // Tên file hiển thị trên Telegram = tên doanh nghiệp
        $displayFilename = $this->buildFilename($company->name) . '.pdf';

        try {
            $response = Http::timeout(30)
                ->attach(
                    'document',
                    fopen($filePath, 'r'),
                    $displayFilename
                )
                ->post($url, [
                    'chat_id' => $config->chat_id,
                    'caption' => $caption,
                ]);

            if ($response->successful() && $response->json('ok')) {
                Log::info('TelegramService: Document sent successfully', [
                    'chat_id' => $config->chat_id,
                    'mst' => $company->mst,
                ]);

                // Update send tracking
                $config->update([
                    'last_sent_at' => now(),
                    'daily_send_count' => $config->daily_send_count + 1,
                ]);

                return true;
            }

            Log::error('TelegramService: API returned error', [
                'chat_id' => $config->chat_id,
                'response' => $response->json(),
                'status' => $response->status(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::error('TelegramService: Failed to send document', [
                'chat_id' => $config->chat_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Build caption for the Telegram message.
     * Hiện: Tên DN, MST, SĐT, Ngày TL, Nguồn dữ liệu.
     */
    private function buildCaption(Company $company): string
    {
        $caption = "{$company->name}\n";
        $caption .= "MST: {$company->mst}\n";
        $caption .= "SĐT: {$company->phone}\n";
        $caption .= "Ngày TL: " . ($company->operation_date ? $company->operation_date->format('d/m/Y') : ($company->registration_date ? $company->registration_date->format('d/m/Y') : 'N/A')) . "\n";
        $caption .= "Nguồn: {$company->source_label}";

        return $caption;
    }

    /**
     * Verify a bot token is valid and get bot info.
     */
    public function verifyBotToken(string $botToken): ?array
    {
        try {
            $url = self::API_BASE . $botToken . '/getMe';
            $response = Http::timeout(10)->get($url);

            if ($response->successful() && $response->json('ok')) {
                return $response->json('result');
            }
        } catch (\Throwable $e) {
            Log::warning('TelegramService: Bot token verification failed', [
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Chuyển tên DN thành tên file hiển thị trên Telegram.
     * VD: "CÔNG TY TNHH ABC" → "CONG-TY-TNHH-ABC"
     */
    private function buildFilename(string $name): string
    {
        $map = [
            'à'=>'a','á'=>'a','ả'=>'a','ã'=>'a','ạ'=>'a',
            'ă'=>'a','ắ'=>'a','ằ'=>'a','ẳ'=>'a','ẵ'=>'a','ặ'=>'a',
            'â'=>'a','ấ'=>'a','ầ'=>'a','ẩ'=>'a','ẫ'=>'a','ậ'=>'a',
            'đ'=>'d',
            'è'=>'e','é'=>'e','ẻ'=>'e','ẽ'=>'e','ẹ'=>'e',
            'ê'=>'e','ế'=>'e','ề'=>'e','ể'=>'e','ễ'=>'e','ệ'=>'e',
            'ì'=>'i','í'=>'i','ỉ'=>'i','ĩ'=>'i','ị'=>'i',
            'ò'=>'o','ó'=>'o','ỏ'=>'o','õ'=>'o','ọ'=>'o',
            'ô'=>'o','ố'=>'o','ồ'=>'o','ổ'=>'o','ỗ'=>'o','ộ'=>'o',
            'ơ'=>'o','ớ'=>'o','ờ'=>'o','ở'=>'o','ỡ'=>'o','ợ'=>'o',
            'ù'=>'u','ú'=>'u','ủ'=>'u','ũ'=>'u','ụ'=>'u',
            'ư'=>'u','ứ'=>'u','ừ'=>'u','ử'=>'u','ữ'=>'u','ự'=>'u',
            'ỳ'=>'y','ý'=>'y','ỷ'=>'y','ỹ'=>'y','ỵ'=>'y',
            'À'=>'A','Á'=>'A','Ả'=>'A','Ã'=>'A','Ạ'=>'A',
            'Ă'=>'A','Ắ'=>'A','Ằ'=>'A','Ẳ'=>'A','Ẵ'=>'A','Ặ'=>'A',
            'Â'=>'A','Ấ'=>'A','Ầ'=>'A','Ẩ'=>'A','Ẫ'=>'A','Ậ'=>'A',
            'Đ'=>'D',
            'È'=>'E','É'=>'E','Ẻ'=>'E','Ẽ'=>'E','Ẹ'=>'E',
            'Ê'=>'E','Ế'=>'E','Ề'=>'E','Ể'=>'E','Ễ'=>'E','Ệ'=>'E',
            'Ì'=>'I','Í'=>'I','Ỉ'=>'I','Ĩ'=>'I','Ị'=>'I',
            'Ò'=>'O','Ó'=>'O','Ỏ'=>'O','Õ'=>'O','Ọ'=>'O',
            'Ô'=>'O','Ố'=>'O','Ồ'=>'O','Ổ'=>'O','Ỗ'=>'O','Ộ'=>'O',
            'Ơ'=>'O','Ớ'=>'O','Ờ'=>'O','Ở'=>'O','Ỡ'=>'O','Ợ'=>'O',
            'Ù'=>'U','Ú'=>'U','Ủ'=>'U','Ũ'=>'U','Ụ'=>'U',
            'Ư'=>'U','Ứ'=>'U','Ừ'=>'U','Ử'=>'U','Ữ'=>'U','Ự'=>'U',
            'Ỳ'=>'Y','Ý'=>'Y','Ỷ'=>'Y','Ỹ'=>'Y','Ỵ'=>'Y',
        ];
        $name = strtr($name, $map);
        $name = preg_replace('/[^A-Za-z0-9]+/', '_', $name);
        $name = trim($name, '_');
        return strtoupper(substr($name, 0, 100));
    }
}
