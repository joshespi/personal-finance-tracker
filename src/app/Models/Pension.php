<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Pension extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'name', 'plan_label', 'membership_date', 'service_credit_years',
        'service_cap_years', 'multiplier_pct', 'final_average_salary', 'salary_growth_pct',
        'cola_pct', 'monthly_benefit_estimate', 'birth_year', 'retirement_age',
        'life_expectancy_age', 'discount_rate_pct', 'include_in_net_worth', 'notes', 'currency',
    ];

    protected $casts = [
        'membership_date'          => 'date',
        'service_credit_years'     => 'decimal:3',
        'service_cap_years'        => 'decimal:3',
        'multiplier_pct'           => 'decimal:3',
        'final_average_salary'     => 'decimal:2',
        'salary_growth_pct'        => 'decimal:2',
        'cola_pct'                 => 'decimal:2',
        'monthly_benefit_estimate' => 'decimal:2',
        'birth_year'               => 'integer',
        'retirement_age'           => 'integer',
        'life_expectancy_age'      => 'integer',
        'discount_rate_pct'        => 'decimal:2',
        'include_in_net_worth'     => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function toBackupArray(): array
    {
        return [
            'name'                     => $this->name,
            'plan_label'               => $this->plan_label,
            'membership_date'          => $this->membership_date?->toDateString(),
            'service_credit_years'     => (float) $this->service_credit_years,
            'service_cap_years'        => $this->service_cap_years !== null ? (float) $this->service_cap_years : null,
            'multiplier_pct'           => (float) $this->multiplier_pct,
            'final_average_salary'     => (float) $this->final_average_salary,
            'salary_growth_pct'        => (float) $this->salary_growth_pct,
            'cola_pct'                 => (float) $this->cola_pct,
            'monthly_benefit_estimate' => $this->monthly_benefit_estimate !== null ? (float) $this->monthly_benefit_estimate : null,
            'birth_year'               => $this->birth_year !== null ? (int) $this->birth_year : null,
            'retirement_age'           => (int) $this->retirement_age,
            'life_expectancy_age'      => (int) $this->life_expectancy_age,
            'discount_rate_pct'        => (float) $this->discount_rate_pct,
            'include_in_net_worth'     => (bool) $this->include_in_net_worth,
            'notes'                    => $this->notes,
            'currency'                 => $this->currency,
        ];
    }
}
