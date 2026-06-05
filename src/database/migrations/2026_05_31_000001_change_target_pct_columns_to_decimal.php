<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('target_stock_pct', 5, 2)->default(0)->change();
            $table->decimal('target_crypto_pct', 5, 2)->default(0)->change();
            $table->decimal('target_real_estate_pct', 5, 2)->default(0)->change();
            $table->decimal('target_bond_pct', 5, 2)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('target_stock_pct')->default(0)->change();
            $table->unsignedTinyInteger('target_crypto_pct')->default(0)->change();
            $table->unsignedTinyInteger('target_real_estate_pct')->default(0)->change();
            $table->unsignedTinyInteger('target_bond_pct')->default(0)->change();
        });
    }
};
