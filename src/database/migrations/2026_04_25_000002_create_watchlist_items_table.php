<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('watchlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('symbol', 20);
            $table->string('name', 200)->nullable();
            $table->enum('asset_type', ['stock', 'crypto'])->default('stock');
            $table->decimal('target_price', 20, 8)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'symbol']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('watchlist_items');
    }
};
