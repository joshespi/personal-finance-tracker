<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // sqlite enforces enum() as a CHECK constraint; drop it by switching to string.
            Schema::table('assets', fn (Blueprint $t) => $t->string('asset_type')->change());
            Schema::table('watchlist_items', fn (Blueprint $t) => $t->string('asset_type')->default('stock')->change());

            return;
        }

        DB::statement("ALTER TABLE assets MODIFY COLUMN asset_type ENUM('stock', 'crypto', 'real_estate') NOT NULL");
        DB::statement("ALTER TABLE watchlist_items MODIFY COLUMN asset_type ENUM('stock', 'crypto', 'real_estate') NOT NULL DEFAULT 'stock'");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            DB::table('assets')->where('asset_type', 'real_estate')->update(['asset_type' => 'stock']);
            DB::table('watchlist_items')->where('asset_type', 'real_estate')->update(['asset_type' => 'stock']);

            return;
        }

        DB::statement("UPDATE assets SET asset_type = 'stock' WHERE asset_type = 'real_estate'");
        DB::statement("UPDATE watchlist_items SET asset_type = 'stock' WHERE asset_type = 'real_estate'");
        DB::statement("ALTER TABLE assets MODIFY COLUMN asset_type ENUM('stock', 'crypto') NOT NULL");
        DB::statement("ALTER TABLE watchlist_items MODIFY COLUMN asset_type ENUM('stock', 'crypto') NOT NULL DEFAULT 'stock'");
    }
};
