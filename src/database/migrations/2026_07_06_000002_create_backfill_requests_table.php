<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backfill_requests', function (Blueprint $table) {
            $table->id();
            $table->json('portfolio_ids');
            $table->date('from_date');
            $table->date('to_date');
            $table->string('status')->default('pending'); // pending | in_progress | completed
            $table->unsignedInteger('total_assets')->default(0);
            $table->json('pending_asset_ids');
            $table->string('last_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backfill_requests');
    }
};
