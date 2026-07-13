<?php

namespace App\Enums;

/**
 * Content blocks a user can opt into for the periodic account-summary email.
 * Single source of truth for both the settings form (one checkbox per case)
 * and EmailSummaryService::compute() (which builds only the sections a user
 * selected). Absence from a user's stored preferences means "off" — unlike
 * DashboardWidget, these are opt-in additions rather than default-visible.
 */
enum EmailSummarySection: string
{
    case Budgeting         = 'budgeting';
    case Investing         = 'investing';
    case NetWorth          = 'net_worth';
    case UpcomingScheduled = 'upcoming_scheduled';
    case CategoryChanges   = 'category_changes';
    case Warnings          = 'warnings';

    public function label(): string
    {
        return match ($this) {
            self::Budgeting         => 'Budgeting activity',
            self::Investing         => 'Investing activity',
            self::NetWorth          => 'Net worth',
            self::UpcomingScheduled => 'Upcoming scheduled transactions',
            self::CategoryChanges   => 'Spending category changes',
            self::Warnings          => 'Warnings & attention items',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Budgeting         => 'Cash transactions and envelope activity for the period.',
            self::Investing         => 'Buy/sell activity and portfolio value change.',
            self::NetWorth          => 'Net worth now, plus portfolio value change since the start of the period.',
            self::UpcomingScheduled => 'Recurring transactions due before the next summary.',
            self::CategoryChanges   => 'Percent change in spending per envelope vs. the prior period.',
            self::Warnings          => 'Envelopes over budget, low balances, and upcoming bills.',
        };
    }

    /** Flat array of string values — for Rule::in / validation. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
