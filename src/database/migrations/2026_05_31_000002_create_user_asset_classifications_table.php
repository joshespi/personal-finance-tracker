<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_asset_classifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('asset_id')->constrained()->cascadeOnDelete();
            $table->string('asset_type');
            $table->timestamps();

            $table->unique(['user_id', 'asset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_asset_classifications');
    }
};
