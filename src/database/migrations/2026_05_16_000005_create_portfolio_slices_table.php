<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portfolio_slices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('target_pct', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['portfolio_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_slices');
    }
};
