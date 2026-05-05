<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_assets', function (Blueprint $table) {
            $table->string('tracking_method')->default('static')->after('currency');
            $table->foreignId('proxy_asset_id')->nullable()->constrained('assets')->nullOnDelete()->after('tracking_method');
            $table->decimal('anchor_value', 15, 2)->nullable()->after('proxy_asset_id');
            $table->date('anchor_date')->nullable()->after('anchor_value');
            $table->decimal('anchor_synthetic_shares', 20, 8)->nullable()->after('anchor_date');
        });
    }

    public function down(): void
    {
        Schema::table('manual_assets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proxy_asset_id');
            $table->dropColumn(['tracking_method', 'anchor_value', 'anchor_date', 'anchor_synthetic_shares']);
        });
    }
};
