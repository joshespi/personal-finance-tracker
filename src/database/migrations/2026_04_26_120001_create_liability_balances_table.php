<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liability_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liability_id')->constrained()->cascadeOnDelete();
            $table->decimal('balance', 20, 8);
            $table->text('notes')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->index(['liability_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liability_balances');
    }
};
