<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backfill_requests', function (Blueprint $table) {
            $table->json('asset_ids')->nullable()->after('pending_asset_ids');
        });
    }

    public function down(): void
    {
        Schema::table('backfill_requests', function (Blueprint $table) {
            $table->dropColumn('asset_ids');
        });
    }
};
