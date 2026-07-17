<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Key-value store cho cấu hình hệ thống.
 * Dùng để lưu Google Sheet URL, Telegram defaults, v.v.
 * Có cache layer để không query DB mỗi lần scrape.
 */
class AppSetting extends Model
{
    protected $fillable = ['key', 'value', 'description'];

    /** Cache TTL: 1 giờ */
    private const CACHE_TTL = 3600;
    private const CACHE_PREFIX = 'app_setting:';

    /**
     * Lấy giá trị setting theo key (có cache).
     */
    public static function getValue(string $key, ?string $default = null): ?string
    {
        return Cache::remember(
            self::CACHE_PREFIX . $key,
            self::CACHE_TTL,
            fn () => static::where('key', $key)->value('value') ?? $default
        );
    }

    /**
     * Set giá trị setting (upsert + clear cache).
     */
    public static function setValue(string $key, ?string $value, ?string $description = null): void
    {
        $data = ['value' => $value];
        if ($description !== null) {
            $data['description'] = $description;
        }

        static::updateOrCreate(['key' => $key], $data);

        Cache::forget(self::CACHE_PREFIX . $key);
    }

    /**
     * Lấy tất cả settings dạng key => value.
     */
    public static function getAllSettings(): array
    {
        return static::pluck('value', 'key')->toArray();
    }

    /**
     * Các key mặc định của hệ thống.
     */
    public const KEY_GOOGLE_SHEET_WEBHOOK = 'google_sheet_webhook_url';
    public const KEY_GOOGLE_SHEET_ENABLED = 'google_sheet_enabled';
}
