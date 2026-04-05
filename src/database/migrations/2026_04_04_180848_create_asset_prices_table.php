<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 20, 8);
            $table->char('currency', 3)->default('USD');
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['asset_id', 'recorded_at']);
            $table->index(['asset_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_prices');
    }
};
