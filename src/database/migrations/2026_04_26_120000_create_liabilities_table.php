<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('manual_asset_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 200);
            $table->enum('liability_type', ['mortgage', 'credit_card', 'auto_loan', 'student_loan', 'personal_loan', 'other']);
            $table->decimal('interest_rate', 6, 3)->nullable();
            $table->text('notes')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->timestamps();

            $table->index(['user_id', 'liability_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liabilities');
    }
};
