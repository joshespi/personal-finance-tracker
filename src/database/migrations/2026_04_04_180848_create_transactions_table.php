<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->restrictOnDelete();
            $table->enum('type', ['buy', 'sell', 'dividend', 'staking_reward', 'transfer_in', 'transfer_out']);
            $table->decimal('quantity', 20, 8);
            $table->decimal('price_per_unit', 20, 8);
            $table->decimal('fees', 20, 8)->default(0);
            $table->char('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamp('transacted_at');
            $table->timestamps();

            $table->index(['portfolio_id', 'transacted_at']);
            $table->index(['portfolio_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
