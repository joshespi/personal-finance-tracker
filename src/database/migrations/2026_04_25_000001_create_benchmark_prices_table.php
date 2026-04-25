<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('benchmark_prices', function (Blueprint $table) {
            $table->id();
            $table->string('ticker', 10); // SPY, BTC
            $table->date('recorded_on');
            $table->decimal('close_price', 20, 8);
            $table->timestamps();

            $table->unique(['ticker', 'recorded_on']);
            $table->index(['ticker', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('benchmark_prices');
    }
};
