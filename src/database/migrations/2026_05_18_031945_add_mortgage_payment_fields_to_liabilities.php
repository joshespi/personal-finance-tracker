<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liabilities', function (Blueprint $table) {
            $table->decimal('escrow_amount', 10, 2)->nullable()->after('minimum_payment');
            $table->tinyInteger('payment_day')->unsigned()->nullable()->after('escrow_amount');
            $table->foreignId('payment_envelope_id')->nullable()->constrained('envelopes')->nullOnDelete()->after('payment_day');
            $table->foreignId('payment_cash_account_id')->nullable()->constrained('cash_accounts')->nullOnDelete()->after('payment_envelope_id');
        });

        Schema::table('scheduled_transactions', function (Blueprint $table) {
            $table->foreignId('liability_id')->nullable()->constrained('liabilities')->cascadeOnDelete()->after('cash_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('scheduled_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('liability_id');
        });

        Schema::table('liabilities', function (Blueprint $table) {
            $table->dropConstrainedForeignId('payment_cash_account_id');
            $table->dropConstrainedForeignId('payment_envelope_id');
            $table->dropColumn(['escrow_amount', 'payment_day']);
        });
    }
};
