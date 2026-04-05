<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_valuations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manual_asset_id')->constrained()->cascadeOnDelete();
            $table->decimal('value', 20, 8);
            $table->text('notes')->nullable();
            $table->timestamp('valued_at');
            $table->timestamps();

            $table->index(['manual_asset_id', 'valued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_valuations');
    }
};
