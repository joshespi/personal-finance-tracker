<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->decimal('interest_rate', 5, 2)->nullable()->after('notes');
            $table->tinyInteger('billing_day')->unsigned()->nullable()->after('interest_rate');
        });
    }

    public function down(): void
    {
        Schema::table('cash_accounts', function (Blueprint $table) {
            $table->dropColumn(['interest_rate', 'billing_day']);
        });
    }
};
