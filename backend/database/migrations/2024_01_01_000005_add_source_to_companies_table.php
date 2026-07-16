<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm cột 'source' để phân biệt DN đến từ nguồn nào:
 * - masothue: masothue.com
 * - tramasothue: tramasothue.com.vn
 * - both: xuất hiện ở cả 2 nguồn
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('source', 20)
                ->default('masothue')
                ->after('mst')
                ->comment('Nguồn dữ liệu: masothue, tramasothue, both');

            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });
    }
};
