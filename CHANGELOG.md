# Changelog

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/).

## [1.8.2] - 2026-07-12

### Changed
- Liability scheduled payments generalized from mortgage-only to any liability type (credit card, auto loan, etc.); label updated to "Liability payment".
- Portfolio CSV transaction import refactored into `TransactionCsvImportService`, reusing the shared CSV reader (adds UTF-8 BOM handling) and `AssetService` for asset creation.
- Realized-gain lot aggregation consolidated into `RealizedGainService::allLotsForUser()`, used by both the tax summary page and the realized-gains export.
- Portfolio slice symbol lookup now does a single query instead of two.

### Fixed
- Liability escrow amount is now cleared server-side when a liability's type isn't "mortgage" (previously a hidden form field could resubmit a stale escrow value after switching a mortgage to another liability type).
- Portfolio slice form now accepts lowercase/mixed-case ticker symbols.
- Forecast page dollar figures now respect demo mode.

## [1.3.0] - 2026-06-04

### Added
- **Invested Assets** — manual assets carry an "Count as invested" flag; a new dashboard tile shows portfolio value minus assets you've excluded (e.g. a primary residence). Excluded assets still count toward Net Worth, Total Assets, allocation, and the trend chart.
- **Portfolio close / archive** — portfolios can be closed (`closed_at`). Closed portfolios drop off the dashboard, hide in a collapsible section on the index, and reject new transactions, but remain in historical and tax reports.
- **Per-user asset classification** — each user can reclassify a shared asset's type for their own allocation/display without affecting the global price feed. Price-source changes are admin-only.
- **Adaptive chart smoothing** — net-worth trend collapses long spans to period-end points (daily ≤3mo, weekly ≤2yr, monthly beyond) while always preserving the latest point.
- **Mortgage payment scheduling** on liabilities; scheduled transactions on cash accounts.
- **Age of Money** badge and a budget-rule drift banner (50/30/20 + emergency-fund phase).
- **Demo / anonymized data mode** for sharing screenshots without exposing real numbers.
- **Email summaries** with an opt-in toggle.
- Credit-card support in the liability type enum.
- Frontend unit tests (vitest) for shared chart helpers.
- Version surfaced in the app footer and `config('app.version')`.

### Changed
- Authorization moved from ad-hoc `abort_unless` ownership checks to Laravel Policies.
- Budget rule corrected to 50/30/20.
- Envelope flow: inline save-on-blur, sort by target amount, prev-month editing, page consolidation, nav reorg.
- Money section reorganized; dashboard tiles centered and restyled.
- Investment targets accept decimals with a live 100% indicator.
- npm builds isolated to their own container.

### Fixed
- Cost basis on portfolio/file transfers; transfers no longer counted as tax events; transfer fee handling.
- Various reconcile, charting, and styling fixes.

### Removed
- Watchlist feature.

## [1.2.0]

Earlier release. History predates this changelog; see git tag `1.2.0`.

## [1.1.0]

See git tag `1.1.0`.

## [1.0.0]

Initial tagged release. See git tag `1.0.0`.

[1.3.0]: https://github.com/joshespi/portfolio-tracker/compare/1.2.0...HEAD
[1.2.0]: https://github.com/joshespi/portfolio-tracker/releases/tag/1.2.0
[1.1.0]: https://github.com/joshespi/portfolio-tracker/releases/tag/1.1.0
[1.0.0]: https://github.com/joshespi/portfolio-tracker/releases/tag/1.0.0
