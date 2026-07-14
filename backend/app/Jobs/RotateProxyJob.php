<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Job đầu tiên trong Chain: gọi TMProxy API lấy IP proxy mới.
 * IP được lưu vào Redis cache key "current_proxy" để các Job sau dùng chung.
 */
class RotateProxyJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 2;

    /** Cache key lưu proxy endpoint hiện tại */
    public const CACHE_KEY = 'current_proxy';

    /** TTL cache proxy (5 phút - TMProxy min 4 phút) */
    public const CACHE_TTL = 300;

    public function handle(): void
    {
        $apiKey = config('scraper.tmproxy_key');

        if (empty($apiKey)) {
            Log::info('RotateProxyJob: No TMProxy key configured, running without proxy.');
            Cache::put(self::CACHE_KEY, null, self::CACHE_TTL);
            return;
        }

        try {
            // Thử lấy proxy hiện tại trước
            $response = Http::timeout(10)->post('https://tmproxy.com/api/proxy/get-current-proxy', [
                'api_key' => $apiKey,
            ]);

            $data = $response->json();

            // Nếu proxy hiện tại không khả dụng, xin IP mới
            if (!isset($data['code']) || $data['code'] !== 0 || empty($data['data']['https'])) {
                $response = Http::timeout(10)->post('https://tmproxy.com/api/proxy/get-new-proxy', [
                    'api_key' => $apiKey,
                ]);
                $data = $response->json();
            }

            if (isset($data['code']) && $data['code'] === 0 && !empty($data['data']['https'])) {
                $proxyUrl = 'http://' . $data['data']['https'];

                Cache::put(self::CACHE_KEY, $proxyUrl, self::CACHE_TTL);

                Log::info('RotateProxyJob: Proxy rotated', [
                    'ip' => $data['data']['public_ip'] ?? 'unknown',
                    'proxy' => $proxyUrl,
                ]);
            } else {
                Log::warning('RotateProxyJob: TMProxy API error', ['response' => $data]);
                Cache::put(self::CACHE_KEY, null, self::CACHE_TTL);
            }
        } catch (\Throwable $e) {
            Log::error('RotateProxyJob: Exception', ['error' => $e->getMessage()]);
            Cache::put(self::CACHE_KEY, null, self::CACHE_TTL);
        }
    }

    /**
     * Helper: lấy proxy URL hiện tại từ cache (dùng trong các Job khác).
     */
    public static function getCurrentProxy(): ?string
    {
        return Cache::get(self::CACHE_KEY);
    }
}
