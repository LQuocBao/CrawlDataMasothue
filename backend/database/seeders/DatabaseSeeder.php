<?php

namespace Database\Seeders;

use App\Models\Filter;
use App\Models\TelegramConfig;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with sample filter and telegram config.
     * Only runs in non-production environments.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        // Create a sample Telegram config
        $telegramConfig = TelegramConfig::create([
            'name' => 'Test Group (Dev)',
            'bot_token' => 'YOUR_BOT_TOKEN_HERE',
            'chat_id' => '-1001234567890',
            'is_active' => true,
        ]);

        // Create sample filters
        Filter::create([
            'name' => 'DN Hà Nội - CNTT',
            'provinces' => ['Hà Nội'],
            'industry_keywords' => ['công nghệ thông tin', 'phần mềm', 'lập trình'],
            'industry_codes' => ['6201', '6202', '6209'],
            'is_active' => true,
            'telegram_config_id' => $telegramConfig->id,
        ]);

        Filter::create([
            'name' => 'DN HCM - Thương mại',
            'provinces' => ['Hồ Chí Minh'],
            'industry_keywords' => ['thương mại', 'xuất nhập khẩu', 'bán buôn'],
            'industry_codes' => ['4610', '4620', '4631'],
            'is_active' => true,
            'telegram_config_id' => $telegramConfig->id,
        ]);

        Filter::create([
            'name' => 'Toàn quốc - Bất động sản',
            'provinces' => [],
            'industry_keywords' => ['bất động sản', 'xây dựng', 'kiến trúc'],
            'industry_codes' => ['6810', '4100', '4290'],
            'is_active' => false, // Inactive by default
            'telegram_config_id' => $telegramConfig->id,
        ]);
    }
}
