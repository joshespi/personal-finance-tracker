<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-frequency send map replaces the single `last_email_summary_sent_at` timestamp, which
 * couldn't tell "this cadence already went out today" from "a different cadence did".
 *
 * No backfill: the old column recorded the last send of *any* cadence, so it can't be
 * attributed to one. Nothing in the app ever read it, so the worst case is that the first
 * run after deploy has no prior record — i.e. it behaves exactly as it does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Map of EmailSummaryFrequency value => that cadence's last-sent period key
            // ('2026-08-17' / '2026-W34' / '2026-08' — see EmailSummaryFrequency::periodKey()).
            $table->json('email_summary_last_sent_at')->nullable()->after('email_summary_sections');
            $table->dropColumn('last_email_summary_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_email_summary_sent_at')->nullable()->after('email_summary_sections');
            $table->dropColumn('email_summary_last_sent_at');
        });
    }
};
