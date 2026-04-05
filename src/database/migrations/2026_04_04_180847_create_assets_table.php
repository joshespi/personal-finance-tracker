<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('symbol', 20)->unique();
            $table->string('name', 200);
            $table->enum('asset_type', ['stock', 'crypto']);
            $table->string('exchange', 50)->nullable();   // e.g. NASDAQ, NYSE
            $table->string('coingecko_id', 100)->nullable();
            $table->string('polygon_ticker', 20)->nullable();
            $table->timestamps();

            $table->index('asset_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
