<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->foreignId('envelope_id')->nullable()->after('cash_account_id')->constrained()->nullOnDelete();
        });

        // Drop orphaned spend records — spending is now recorded as tagged cash withdrawals.
        DB::table('envelope_transactions')->where('type', 'spend')->delete();
    }

    public function down(): void
    {
        Schema::table('cash_transactions', function (Blueprint $table) {
            $table->dropForeign(['envelope_id']);
            $table->dropColumn('envelope_id');
        });
    }
};
