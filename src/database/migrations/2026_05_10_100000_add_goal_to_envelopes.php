<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            $table->decimal('goal_amount', 12, 2)->nullable()->after('monthly_target');
            $table->date('goal_date')->nullable()->after('goal_amount');
        });
    }

    public function down(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            $table->dropColumn(['goal_amount', 'goal_date']);
        });
    }
};
