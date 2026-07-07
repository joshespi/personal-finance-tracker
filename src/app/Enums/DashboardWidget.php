<?php

namespace App\Enums;

/**
 * Every toggleable block on the dashboard — whole sections and individual stat
 * tiles. Single source of truth for both the settings form (which renders one
 * checkbox per case, grouped by group()) and the dashboard view (which gates
 * each block on User::showsWidget()). Absence from a user's stored preferences
 * means "visible", so adding a case here defaults to on for existing users.
 */
enum DashboardWidget: string
{
    // Sections
    case InvestmentStats     = 'investment_stats';
    case FinancialPicture    = 'financial_picture';
    case NetWorthChart       = 'net_worth_chart';
    case CalendarHeatmap     = 'calendar_heatmap';
    case Benchmark           = 'benchmark';
    case Allocation          = 'allocation';
    case Rebalancing         = 'rebalancing';
    case Holdings            = 'holdings';
    case Portfolios          = 'portfolios';
    case InterestBleedBanner = 'interest_bleed_banner';
    case BudgetDriftBanner   = 'budget_drift_banner';

    // Investment Portfolio tiles
    case TilePortfolioValue = 'tile_portfolio_value';
    case TileInvestedValue  = 'tile_invested_value';
    case TileCostBasis      = 'tile_cost_basis';
    case TileMarketValue    = 'tile_market_value';
    case TileUnrealized     = 'tile_unrealized';
    case TilePl             = 'tile_pl';

    // Full Financial Picture tiles
    case TileCash             = 'tile_cash';
    case TileTotalAssets      = 'tile_total_assets';
    case TileTotalDebt        = 'tile_total_debt';
    case TileRetirementIncome = 'tile_retirement_income';
    case TilePensionValue     = 'tile_pension_value';
    case TileNetWorth         = 'tile_net_worth';
    case TileDebtToAsset      = 'tile_debt_to_asset';
    case TileEmergencyFund    = 'tile_emergency_fund';
    case TileReadyToAssign    = 'tile_ready_to_assign';
    case TileMonthlySpend     = 'tile_monthly_spend';
    case TileAgeOfMoney       = 'tile_age_of_money';

    public const GROUP_SECTIONS = 'Sections';

    public const GROUP_INVESTMENT = 'Investment Portfolio tiles';

    public const GROUP_FINANCIAL = 'Full Financial Picture tiles';

    public function label(): string
    {
        return match ($this) {
            self::InvestmentStats     => 'Investment Portfolio stats',
            self::FinancialPicture    => 'Full Financial Picture stats',
            self::NetWorthChart       => 'Portfolio value chart',
            self::CalendarHeatmap     => 'Daily change calendar',
            self::Benchmark           => 'Benchmark comparison chart',
            self::Allocation          => 'Asset allocation donut',
            self::Rebalancing         => 'Global rebalancing table',
            self::Holdings            => 'All holdings table',
            self::Portfolios          => 'Portfolios breakdown',
            self::InterestBleedBanner => 'Interest-bleed alert',
            self::BudgetDriftBanner   => 'Budget-rule drift alert',

            self::TilePortfolioValue => 'Portfolio Value',
            self::TileInvestedValue  => 'Invested Assets',
            self::TileCostBasis      => 'Cost Basis',
            self::TileMarketValue    => 'Market Value',
            self::TileUnrealized     => 'Unrealized P&L',
            self::TilePl             => 'Period Gain/Loss',

            self::TileCash             => 'Cash Balance',
            self::TileTotalAssets      => 'Total Assets',
            self::TileTotalDebt        => 'Total Debt',
            self::TileRetirementIncome => 'Retirement Income',
            self::TilePensionValue     => 'Pension Value',
            self::TileNetWorth         => 'Net Worth',
            self::TileDebtToAsset      => 'Debt-to-Asset',
            self::TileEmergencyFund    => 'Emergency Fund',
            self::TileReadyToAssign    => 'Ready to Assign',
            self::TileMonthlySpend     => 'Monthly Spend',
            self::TileAgeOfMoney       => 'Age of Money',
        };
    }

    public function group(): string
    {
        return match ($this) {
            self::InvestmentStats, self::FinancialPicture, self::NetWorthChart, self::CalendarHeatmap,
            self::Benchmark, self::Allocation, self::Rebalancing, self::Holdings,
            self::Portfolios, self::InterestBleedBanner, self::BudgetDriftBanner => self::GROUP_SECTIONS,

            self::TilePortfolioValue, self::TileInvestedValue, self::TileCostBasis,
            self::TileMarketValue, self::TileUnrealized, self::TilePl => self::GROUP_INVESTMENT,

            default => self::GROUP_FINANCIAL,
        };
    }

    /** Flat array of string values — for validation and building the visibility map. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Cases bucketed by group() in declaration order, for rendering the settings
     * form as labelled groups.
     *
     * @return array<string, list<self>>
     */
    public static function grouped(): array
    {
        $groups = [
            self::GROUP_SECTIONS   => [],
            self::GROUP_INVESTMENT => [],
            self::GROUP_FINANCIAL  => [],
        ];

        foreach (self::cases() as $case) {
            $groups[$case->group()][] = $case;
        }

        return $groups;
    }
}
