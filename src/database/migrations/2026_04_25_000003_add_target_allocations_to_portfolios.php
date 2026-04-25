<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_stock_pct')->default(0)->after('currency');
            $table->unsignedTinyInteger('target_crypto_pct')->default(0)->after('target_stock_pct');
            $table->unsignedTinyInteger('target_manual_pct')->default(0)->after('target_crypto_pct');
        });
    }

    public function down(): void
    {
        Schema::table('portfolios', function (Blueprint $table) {
            $table->dropColumn(['target_stock_pct', 'target_crypto_pct', 'target_manual_pct']);
        });
    }
};
