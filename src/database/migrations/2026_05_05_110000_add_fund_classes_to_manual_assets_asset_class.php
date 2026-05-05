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

        DB::statement("ALTER TABLE manual_assets MODIFY COLUMN asset_class ENUM('real_estate','vehicle','collectible','business','other','stock','crypto','bond') NOT NULL");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("UPDATE manual_assets SET asset_class = 'other' WHERE asset_class IN ('stock','crypto','bond')");
        DB::statement("ALTER TABLE manual_assets MODIFY COLUMN asset_class ENUM('real_estate','vehicle','collectible','business','other') NOT NULL");
    }
};
