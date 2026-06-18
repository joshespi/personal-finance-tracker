<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            if (! Schema::hasColumn('envelopes', 'is_savings')) {
                $table->boolean('is_savings')->default(false);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'emergency_fund_target_months')) {
                $table->unsignedTinyInteger('emergency_fund_target_months')->default(6);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('envelopes', 'is_savings')) {
            Schema::table('envelopes', function (Blueprint $table) {
                $table->dropColumn('is_savings');
            });
        }

        if (Schema::hasColumn('users', 'emergency_fund_target_months')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('emergency_fund_target_months');
            });
        }
    }
};
