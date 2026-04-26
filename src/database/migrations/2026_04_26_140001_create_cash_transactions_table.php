<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_account_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['deposit', 'withdrawal']);
            $table->decimal('amount', 20, 8);
            $table->string('description', 500)->nullable();
            $table->date('occurred_at');
            $table->timestamps();

            $table->index(['cash_account_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_transactions');
    }
};
