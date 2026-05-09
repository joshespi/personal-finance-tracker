<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scheduled_transactions', function (Blueprint $table) {
            $table->index(['user_id', 'is_active', 'next_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_active', 'next_due_at']);
        });
    }
};
