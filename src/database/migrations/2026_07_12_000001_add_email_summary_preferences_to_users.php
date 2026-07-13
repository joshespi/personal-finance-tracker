<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // List of EmailSummaryFrequency values the user is opted into (e.g. ["weekly","monthly"]). Null/empty = off.
            $table->json('email_summary_frequencies')->nullable()->after('dashboard_preferences');
            // List of EmailSummarySection values to include in the summary email. Null/empty = none.
            $table->json('email_summary_sections')->nullable()->after('email_summary_frequencies');
            $table->timestamp('last_email_summary_sent_at')->nullable()->after('email_summary_sections');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['email_summary_frequencies', 'email_summary_sections', 'last_email_summary_sent_at']);
        });
    }
};
