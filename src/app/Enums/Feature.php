<?php

namespace App\Enums;

use App\Enums\Concerns\HasValues;
use Illuminate\Validation\Rule;

/**
 * Top-level modules a user can switch on or off. Stored per user as a map of
 * value => bool in users.features; a missing key means enabled, so adding a case
 * here defaults to on for existing users. Hiding a feature never deletes its data.
 */
enum Feature: string
{
    use HasValues;

    case Investing   = 'investing';
    case Cash        = 'cash';
    case Retirement  = 'retirement';

    /** Validation rules for a submitted checkbox list of features (at least one required). */
    public static function selectionRules(): array
    {
        return [
            'features'   => ['required', 'array', 'min:1'],
            'features.*' => ['string', Rule::in(self::values())],
        ];
    }

    /**
     * Turns a submitted checkbox list into the stored value => bool map, one explicit
     * entry per feature. Unknown values are ignored.
     *
     * @param  list<string>  $selected
     * @return array<string, bool>
     */
    public static function mapFromSelection(array $selected): array
    {
        $chosen = array_flip($selected);

        return collect(self::cases())
            ->mapWithKeys(fn (self $f) => [$f->value => isset($chosen[$f->value])])
            ->all();
    }

    public function label(): string
    {
        return match ($this) {
            self::Investing  => 'Investing',
            self::Cash       => 'Cash & Budgeting',
            self::Retirement => 'Retirement',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Investing  => 'Portfolios, transactions, dividends, tax reporting, allocation and rebalancing.',
            self::Cash       => 'Cash accounts, envelopes, income, scheduled transactions, debt, cashflow and emergency fund.',
            self::Retirement => 'Retirement and FIRE projections, plus pensions.',
        };
    }
}
