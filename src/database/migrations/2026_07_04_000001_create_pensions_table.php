<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pensions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('URS Pension');
            $table->string('plan_label')->nullable();
            $table->date('membership_date')->nullable();
            $table->decimal('service_credit_years', 6, 3)->default(0);
            $table->decimal('service_cap_years', 6, 3)->nullable();
            $table->decimal('multiplier_pct', 5, 3)->default(2.000);
            $table->decimal('final_average_salary', 12, 2)->default(0);
            $table->decimal('salary_growth_pct', 5, 2)->default(0);
            $table->decimal('cola_pct', 5, 2)->default(4.00);
            $table->decimal('monthly_benefit_estimate', 12, 2)->nullable();
            $table->unsignedSmallInteger('birth_year')->nullable();
            $table->unsignedTinyInteger('retirement_age')->default(52);
            $table->unsignedTinyInteger('life_expectancy_age')->default(90);
            $table->decimal('discount_rate_pct', 5, 2)->default(2.00);
            $table->boolean('include_in_net_worth')->default(true);
            $table->text('notes')->nullable();
            $table->char('currency', 3)->default('USD');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pensions');
    }
};
