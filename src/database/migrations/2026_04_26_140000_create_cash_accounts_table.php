<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->enum('account_type', ['checking', 'savings', 'cash', 'money_market', 'cd', 'other']);
            $table->char('currency', 3)->default('USD');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'account_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_accounts');
    }
};
