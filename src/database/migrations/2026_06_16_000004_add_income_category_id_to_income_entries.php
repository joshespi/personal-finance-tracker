<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('income_entries', function (Blueprint $table) {
            $table->foreignId('income_category_id')->nullable()->after('description')
                ->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('income_entries', function (Blueprint $table) {
            $table->dropConstrainedForeignId('income_category_id');
        });
    }
};
