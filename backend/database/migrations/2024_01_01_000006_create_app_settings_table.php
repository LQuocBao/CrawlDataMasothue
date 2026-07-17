<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng key-value cho cấu hình hệ thống.
 * Cho phép thay đổi Google Sheet URL, Telegram defaults, v.v. từ Dashboard
 * mà không cần sửa code hay .env.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // Seed default settings
        $defaults = [
            [
                'key' => 'google_sheet_webhook_url',
                'value' => 'https://script.google.com/macros/s/AKfycbyIH3Ad1O6NbItD7C_hav21vNkc9dhl63j8AHTVFTqzBAoExtpwaYxzFh1cvl2ypop0/exec',
                'description' => 'Google Apps Script webhook URL để ghi dữ liệu DN vào Sheet',
            ],
            [
                'key' => 'google_sheet_enabled',
                'value' => '1',
                'description' => 'Bật/tắt ghi Google Sheet (1=bật, 0=tắt)',
            ],
        ];

        foreach ($defaults as $setting) {
            \App\Models\AppSetting::create($setting);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
    }
};
