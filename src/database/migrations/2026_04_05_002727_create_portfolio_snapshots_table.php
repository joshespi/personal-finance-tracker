<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->decimal('cost_basis', 20, 8)->default(0);
            $table->decimal('market_value', 20, 8)->default(0);
            $table->decimal('manual_value', 20, 8)->default(0);
            $table->date('recorded_on');
            $table->timestamps();
            $table->unique(['portfolio_id', 'recorded_on']);
            $table->index(['portfolio_id', 'recorded_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_snapshots');
    }
};
