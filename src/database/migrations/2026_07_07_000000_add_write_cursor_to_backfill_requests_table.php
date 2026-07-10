<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backfill_requests', function (Blueprint $table) {
            $table->date('write_cursor')->nullable()->after('pending_asset_ids');
        });

        // Rows completed before write_cursor existed would otherwise show "0/N days"
        // forever (writtenDays() treats a null cursor as zero progress, and completed
        // rows are never revisited by assets:process-backfill-queue) — give them a
        // cursor past their own to_date so they read as fully written.
        DB::table('backfill_requests')->where('status', 'completed')->get(['id', 'to_date'])
            ->each(fn ($row) => DB::table('backfill_requests')->where('id', $row->id)->update([
                'write_cursor' => Carbon::parse($row->to_date)->addDay()->toDateString(),
            ]));
    }

    public function down(): void
    {
        Schema::table('backfill_requests', function (Blueprint $table) {
            $table->dropColumn('write_cursor');
        });
    }
};
