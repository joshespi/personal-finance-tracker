<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            // Lets a non-mandatory envelope (e.g. groceries, fuel) count toward the
            // emergency-fund target without reclassifying it as a 50% Need.
            $table->boolean('include_in_emergency_fund')->default(false)->after('is_mandatory');
        });
    }

    public function down(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            $table->dropColumn('include_in_emergency_fund');
        });
    }
};
