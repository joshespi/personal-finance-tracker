<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // envelopes: emergency fund flags (2026_05_09_000001)
        Schema::table('envelopes', function (Blueprint $table) {
            if (! Schema::hasColumn('envelopes', 'is_mandatory')) {
                $table->boolean('is_mandatory')->default(false);
            }
            if (! Schema::hasColumn('envelopes', 'is_emergency_fund')) {
                $table->boolean('is_emergency_fund')->default(false);
            }
        });

        // income_entries table (2026_05_09_000002)
        if (! Schema::hasTable('income_entries')) {
            Schema::create('income_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->decimal('amount', 20, 8);
                $table->string('description', 500)->nullable();
                $table->date('occurred_at');
                $table->timestamps();
                $table->index(['user_id', 'occurred_at']);
            });
        }

        // portfolios: tax-advantaged flag (2026_05_10_025355)
        if (! Schema::hasColumn('portfolios', 'is_tax_advantaged')) {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->boolean('is_tax_advantaged')->default(false);
            });
        }

        // envelopes: savings goal fields (2026_05_10_100000)
        Schema::table('envelopes', function (Blueprint $table) {
            if (! Schema::hasColumn('envelopes', 'goal_amount')) {
                $table->decimal('goal_amount', 12, 2)->nullable();
            }
            if (! Schema::hasColumn('envelopes', 'goal_date')) {
                $table->date('goal_date')->nullable();
            }
        });

        // scheduled_transactions table (2026_05_06_000001)
        if (! Schema::hasTable('scheduled_transactions')) {
            Schema::create('scheduled_transactions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('description');
                $table->decimal('amount', 20, 8);
                $table->string('type'); // 'envelope' or 'cash'
                $table->foreignId('envelope_id')->nullable()->constrained()->nullOnDelete();
                $table->foreignId('cash_account_id')->nullable()->constrained()->nullOnDelete();
                $table->string('transaction_type'); // 'spend'/'fund' or 'deposit'/'withdrawal'
                $table->string('recurrence'); // 'weekly','biweekly','monthly','quarterly','yearly'
                $table->date('next_due_at');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->index(['user_id', 'is_active', 'next_due_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::table('envelopes', function (Blueprint $table) {
            $table->dropColumn(array_filter([
                Schema::hasColumn('envelopes', 'is_mandatory') ? 'is_mandatory' : null,
                Schema::hasColumn('envelopes', 'is_emergency_fund') ? 'is_emergency_fund' : null,
                Schema::hasColumn('envelopes', 'goal_amount') ? 'goal_amount' : null,
                Schema::hasColumn('envelopes', 'goal_date') ? 'goal_date' : null,
            ]));
        });

        if (Schema::hasColumn('portfolios', 'is_tax_advantaged')) {
            Schema::table('portfolios', function (Blueprint $table) {
                $table->dropColumn('is_tax_advantaged');
            });
        }

        Schema::dropIfExists('income_entries');
        Schema::dropIfExists('scheduled_transactions');
    }
};
