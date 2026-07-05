<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('filters', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Filter set name');
            $table->json('provinces')->nullable()->comment('Array of province codes/names to match');
            $table->json('industry_keywords')->nullable()->comment('Array of keyword strings to match against industry descriptions');
            $table->json('industry_codes')->nullable()->comment('Array of specific VSIC industry codes');
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('telegram_config_id')->nullable();
            $table->timestamps();

            $table->foreign('telegram_config_id')
                ->references('id')
                ->on('telegram_configs')
                ->nullOnDelete();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('filters');
    }
};
