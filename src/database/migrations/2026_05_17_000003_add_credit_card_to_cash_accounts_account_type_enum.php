<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cash_accounts MODIFY account_type ENUM('checking','savings','credit_card','cash','money_market','cd','other') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("ALTER TABLE cash_accounts MODIFY account_type ENUM('checking','savings','cash','money_market','cd','other') NOT NULL");
    }
};
