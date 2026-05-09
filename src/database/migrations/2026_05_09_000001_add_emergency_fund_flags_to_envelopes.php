<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            $table->boolean('is_mandatory')->default(false)->after('sort_order');
            $table->boolean('is_emergency_fund')->default(false)->after('is_mandatory');
        });
    }

    public function down(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            $table->dropColumn(['is_mandatory', 'is_emergency_fund']);
        });
    }
};
