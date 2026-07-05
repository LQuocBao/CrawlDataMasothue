<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('mst', 20)->unique()->comment('Ma so thue (Tax ID)');
            $table->string('name')->comment('Company name');
            $table->string('international_name')->nullable();
            $table->string('short_name')->nullable();
            $table->text('address')->nullable();
            $table->string('province', 100)->nullable()->index();
            $table->string('district', 100)->nullable();
            $table->string('representative')->nullable()->comment('Legal representative');
            $table->string('representative_title')->nullable();
            $table->string('phone', 20)->nullable();
            $table->date('registration_date')->nullable();
            $table->date('operation_date')->nullable();
            $table->string('status', 50)->default('active');
            $table->json('industries')->nullable()->comment('Array of industry codes and descriptions');
            $table->string('managing_tax_authority')->nullable();
            $table->boolean('notification_sent')->default(false);
            $table->timestamp('scraped_at')->useCurrent();
            $table->timestamps();

            $table->index(['province', 'created_at']);
            $table->index('notification_sent');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
