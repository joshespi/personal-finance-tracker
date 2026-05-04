<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return; // SQLite doesn't enforce ENUMs; the app-layer validation is the constraint
        }

        DB::statement("ALTER TABLE assets MODIFY COLUMN asset_type ENUM('stock', 'crypto', 'real_estate') NOT NULL");
        DB::statement("ALTER TABLE watchlist_items MODIFY COLUMN asset_type ENUM('stock', 'crypto', 'real_estate') NOT NULL DEFAULT 'stock'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        DB::statement("UPDATE assets SET asset_type = 'stock' WHERE asset_type = 'real_estate'");
        DB::statement("UPDATE watchlist_items SET asset_type = 'stock' WHERE asset_type = 'real_estate'");
        DB::statement("ALTER TABLE assets MODIFY COLUMN asset_type ENUM('stock', 'crypto') NOT NULL");
        DB::statement("ALTER TABLE watchlist_items MODIFY COLUMN asset_type ENUM('stock', 'crypto') NOT NULL DEFAULT 'stock'");
    }
};
