<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('liabilities', function (Blueprint $table) {
            if (! Schema::hasColumn('liabilities', 'minimum_payment')) {
                $table->decimal('minimum_payment', 10, 2)->nullable()->after('interest_rate');
            }
        });
    }

    public function down(): void
    {
        Schema::table('liabilities', function (Blueprint $table) {
            if (Schema::hasColumn('liabilities', 'minimum_payment')) {
                $table->dropColumn('minimum_payment');
            }
        });
    }
};
