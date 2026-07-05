<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('filters', function (Blueprint $table) {
            // Số ngày gần nhất tính từ ngày đăng ký (VD: 3 = lấy DN đăng ký trong 3 ngày qua)
            $table->unsignedSmallInteger('registration_days_back')
                ->nullable()
                ->after('industry_codes')
                ->comment('Only match companies registered within this many days');

            // Yêu cầu DN phải có số điện thoại mới gửi thông báo
            $table->boolean('require_phone')
                ->default(false)
                ->after('registration_days_back')
                ->comment('Only match companies that have a phone number');
        });
    }

    public function down(): void
    {
        Schema::table('filters', function (Blueprint $table) {
            $table->dropColumn(['registration_days_back', 'require_phone']);
        });
    }
};
