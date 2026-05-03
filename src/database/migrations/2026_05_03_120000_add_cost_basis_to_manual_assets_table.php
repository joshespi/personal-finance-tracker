<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('manual_assets', function (Blueprint $table) {
            $table->decimal('cost_basis', 20, 2)->nullable()->after('asset_class');
        });
    }

    public function down(): void
    {
        Schema::table('manual_assets', function (Blueprint $table) {
            $table->dropColumn('cost_basis');
        });
    }
};
