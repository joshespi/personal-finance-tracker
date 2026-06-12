<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_snapshots', function (Blueprint $table) {
            // Portion of manual_value belonging to assets flagged out of "invested"
            // (e.g. primary residence). Lets the chart plot an invested-only series
            // by subtracting this from the full value.
            $table->decimal('excluded_value', 20, 8)->default(0)->after('manual_value');
        });
    }

    public function down(): void
    {
        Schema::table('portfolio_snapshots', function (Blueprint $table) {
            $table->dropColumn('excluded_value');
        });
    }
};
