<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_assets', function (Blueprint $table) {
            $table->boolean('include_in_chart')->default(true)->after('anchor_synthetic_shares');
        });
    }

    public function down(): void
    {
        Schema::table('manual_assets', function (Blueprint $table) {
            $table->dropColumn('include_in_chart');
        });
    }
};
