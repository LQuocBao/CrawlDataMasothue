<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telegram_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Config label for identification');
            $table->string('bot_token')->comment('Telegram Bot API token');
            $table->string('chat_id')->comment('Target chat/group/channel ID');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_sent_at')->nullable();
            $table->unsignedInteger('daily_send_count')->default(0);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_configs');
    }
};
