# Changelog

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and the project adheres to [Semantic Versioning](https://semver.org/).


## [1.11.0] - 2026-08-04

### Added

- **Sortable ledger columns** — every column header on the single-account ledger (`/cash-accounts/{id}`) and the All Accounts ledger is now a sort control: Account, Date, Type, Status, Description, Envelope / Category, Outflow, and Inflow. Clicking a header sorts by it (text columns ascending first, dates/amounts/status descending first); clicking the active header again reverses the direction. The default remains newest-first, and sorting survives the search filter. Outflow and Inflow sort independently rather than both on the raw amount — rows of the opposite type count as zero, so sorting by Outflow brings the largest spends to the top and pushes deposits to the bottom. Envelope / Category sorts across both relations at once, interleaving envelope-tagged withdrawals with income-category-tagged deposits alphabetically.
- **Credit-card debt now counts in Debt Payoff and Allocator.** Resolves the product decision flagged in [1.10.0]: credit-card `CashAccount`s with a balance owed are now folded into the snowball/avalanche payoff simulation and the extra-cash allocator's debt-priority ranking alongside real `Liability` rows, ranked together by APR. Deliberately **not** added to net worth's `total_debt` — a card's negative balance already reduces `total_cash` there, so counting it twice would double-subtract it.
- A transaction's account/portfolio can now be changed from its edit form (a "move to another portfolio" dropdown), guarded so a linked transfer leg can't be moved independently of its pair (which would desync the two sides).

### Fixed

- The version shown in the app footer was stuck at 1.9.0 — `config/app.php` wasn't bumped for the 1.10.0 or 1.10.1 releases.
- **A single rate-limited (HTTP 429) Finnhub/CoinGecko request was silently retried 3 times instead of backing off immediately.** [1.10.1]'s `->retry(3, 200)` retries any non-2xx response by default when no `when` callback says otherwise — so a provider that had just said "back off" got hit two more times per asset, and `assets:fetch-prices`/the historical backfill's "stop on first 429" logic only ever saw the *last* attempt's status, never the first. Both `FinnhubClient` and `CoinGeckoClient` now exclude 429 from retry while still retrying genuine transient failures (5xx, timeouts).
- A genuine connection failure (timeout, DNS, refused) during hourly price fetching or benchmark fetching threw uncaught out of the current asset/benchmark's loop, aborting the *entire* run — a CoinGecko outage skipped the Finnhub stock fetch that runs right after it, and one flaky stock ticker aborted every ticker later in the loop. Now isolated per call: logged and skipped, not fatal to the run.
- Scheduler failures had no log trail: several `FetchAssetPrices`/`FetchBenchmarkPrices` warnings only wrote to console output, which the scheduler discards (no `sendOutputTo()`), making a stopped-pricing ticker invisible to an operator. Now backed by `Log::warning`/`Log::error` too.
- Multi-cycle scheduled liability-payment catch-up (a user who falls more than one billing cycle behind) computed every overdue cycle's interest/principal off the *same* starting balance instead of compounding — the cached `latestBalance` relation was never refreshed between cycles in the same materialization run.
- Realized-gain proceeds/gain silently dropped a Sell transaction's cash fee entirely (only the buy side folded fees into cost), overstating realized gains — and the tax summary — by the fee amount whenever a sale recorded a fee.
- Portfolio time-weighted return used a transaction's raw `fees` column instead of the fee-in-asset-aware `usdFee()`, skewing TWR for any `fee_in_asset` transfer.
- The generic CSV importer silently dated an unparseable row "today" instead of skipping it, unlike the dedicated transactions importer — a malformed date cell could quietly corrupt cashflow/budget history with no warning.
- Dashboard holdings table's expandable "Held in" row had a hardcoded `colspan="9"`, one column too many for non-admin users (the 9th column is admin-only), leaving a phantom empty column.
- Several icon-only controls (mobile quick-add/dark-mode toggle, envelope month navigation, envelope edit/delete) had no `aria-label`.
- An admin editing their own account (or the last remaining admin) could uncheck "Admin" and lock everyone out of `/admin`, with no in-app recovery path. Both self-demotion and last-admin removal are now blocked.
- Manually verifying or deleting a user from the admin panel wrote no `ActivityLog` entry, unlike every other admin action.

### Security

- Finnhub's API token (sent as a URL query parameter) was leaking into `storage/logs/laravel.log` whenever a connection failure's exception message — which embeds the full request URI — got logged. Token is now redacted before any such message is logged.
- `User::is_admin` is no longer mass-assignable, closing off a theoretical future `User::create($request->all())`-style mistake. No live exploit existed — every current write path already used a validated field list.

## [1.10.1] - 2026-07-22

### Fixed

- **Transfers are now atomic.** Cash-account and portfolio transfers each write two linked rows (withdrawal + deposit / `transfer_out` + `transfer_in`); these were created outside a transaction, so a failure on the second write left an orphaned half-transfer — money debited from one side with no matching credit, and ledgers that no longer reconciled. Both `CashTransferController` and `PortfolioTransferController` now wrap the pair in `DB::transaction()`, matching the pattern already used by `EnvelopeTransactionController`.
- Cash-transaction description search no longer treats `%`/`_` in the filter as SQL LIKE wildcards — searching for `50%` now matches literally instead of every row.
- Envelope "Assigned" amounts couldn't go negative, so there was no way to pull money back out of an envelope's carried-over balance into Ready to Assign — only the current month's own assignment could be lowered, and only down to zero. The assigned-amount input (and `EnvelopeController::assignOne()`'s validation) now accepts negative values, matching YNAB's behavior; the existing delta-based accounting already handled negative deltas correctly.

### Changed

- Finnhub and CoinGecko API calls now retry transient failures (`->retry(3, 200)`), so a single dropped request or HTTP 429/5xx no longer loses a whole price fetch.
- `assets:fetch-prices` stops the run on a sustained HTTP 429 (rather than hammering every remaining ticker), batches its price inserts into one write per feed instead of a row-at-a-time INSERT, and paces Finnhub calls at 1s to respect the free-tier 60/min limit.

## [1.10.0] - 2026-07-22

### Added
- **Savings reconciliation** — an envelope can now be linked to one of the user's savings-type cash accounts (savings, money market, CD) via a "Lives in account" field on the envelope form. The linked account's show page gains a Savings Reconciliation panel comparing what its linked envelopes say should be held against the account's actual balance (short/over/even), plus a count of this month's withdrawals from that account that weren't tagged to any envelope.
- **Interactive 50/30/20 calculator** — the Budget Rule tab on `/analysis` now includes a "try any amount" widget: type any monthly income and see the Needs/Wants/Wealth-Building split recompute live, seeded from your real trailing income.
- Scheduled-transaction materialization now runs on every authenticated request (`MaterializeDueScheduledTransactions` middleware), not only when opening the All Transactions ledger — envelope balances, Ready to Assign, and debt figures no longer depend on which page you open first. The daily `transactions:materialize` cron remains the sole trigger for the scheduled-transaction summary email.

### Changed
- Income is now recorded exclusively as a cash transaction tagged with an income category; the parallel "quick income entry" feature (`IncomeEntry` model/controller/policy, `/income-entries`) is removed, along with the last remnants of the earlier watchlist removal ([1.7.0]'s `WatchlistItem` model/factory).
- Full-backup export no longer includes `income_entries` or `watchlist` keys, and is now built from a `toBackupArray()` method on each exportable model instead of inline per-model closures in `ExportController`.
- Daily portfolio snapshot cron (`portfolios:snapshot`) now shares its valuation algorithm with the admin manual-backfill tool via `PortfolioSnapshotBackfillService`, instead of maintaining a separate holdings computation.
- Policy authorization (`view`/`update`/`delete`) across all owned-model policies deduplicated into a shared `AuthorizesOwner` concern.
- Portfolio allocation and slice-rebalancing calculations extracted from `PortfolioController` into `PortfolioAllocationService`.
- Fee/quantity math consolidated onto `Transaction` (`usdFee()`, `quantityWithAssetFee()`, `netOfFee()`), replacing duplicated inline calculations in `RealizedGainService`, `EmailSummaryService`, `PortfolioTransferController`, and the transaction edit form.
- Proxy-ticker manual asset valuation and cash-account balance summation extracted into reusable model methods (`ManualAsset::proxyValueAt()`, `CashAccount::currentBalanceFromSums()`), replacing hand-duplicated copies in the snapshot backfill service, email summary warnings, and the navigation sidebar.
- Analysis/Planning page tab switchers now share a single `<x-tab-nav>` component instead of duplicated markup.
- Removed the redundant direct cash-transaction "deposit" creation route, which duplicated the shared Livewire ledger form path.
- Stat-tile figures use a tighter, first-line-only responsive font-size clamp so large numbers no longer oversize on narrow tiles.
- The two Livewire ledger views (`transaction-list.blade.php`, `all-transactions.blade.php`) shared ~90% identical markup; extracted into four `resources/views/livewire/partials/*` includes (balance card, scheduled panel, record-transaction form, transaction table) parameterized for the single-account vs. cross-account cases. Removed the unused `alpinejs` devDependency (Livewire boots its own copy; a second import previously broke `wire:*` bindings app-wide) and added a Vitest guard that fails the build if anything reintroduces it.
- Analysis/Planning/Forecast/Dividends pages no longer hand-roll Chart.js in inline `<script>` blocks with their own $-formatter reimplementations — each now has a real Vite entry (`analysis-charts.js`, `planning-charts.js`, `forecast-charts.js`, `dividends-charts.js`) importing the shared `chart-utils`/`format-utils` helpers, with the Blade views reduced to a small JSON data blob.
- The debt-payoff snowball/avalanche simulation was implemented twice — once in `DebtPayoffService::simulate()` (PHP, initial page render) and again in a ~50-line hand-rolled Alpine method (JS, the live "extra payment" slider). The slider now calls a debounced `POST /planning/debt-payoff/simulate` endpoint backed by the same `DebtPayoffService`, so there is exactly one implementation of the payoff algorithm.
- `RealizedGainService` split: its FIFO open-lot walk was duplicated between `compute()` (realized gains) and `openLotsForAsset()` (transfer cost-basis lookup) — both now share one `buildOpenLots()`. Time-weighted return (`computeTwr()`, an unrelated value-performance calculation that only lived here because it also took a `Portfolio`) moved to a new `PortfolioPerformanceService`.
- **Unrealized cost basis is now FIFO, matching realized gains** (`Transaction::accumulateFifoCostBasis()`, replacing the old weighted-average `accumulateCostBasis()`). Previously the portfolio page's unrealized gain used a blended average cost while realized gains (tax summary, CSV export) used FIFO lots — the two didn't reconcile for a position that had been partially sold. This changes displayed `total_cost`/`avg_cost`/`unrealized_gain`/`unrealized_pct` figures (on the portfolio show page, dashboard net-worth rollup, and `portfolio_snapshots.cost_basis` going forward) for any position with multiple buy lots at different prices *and* at least one partial sell; quantity and market value are unaffected. Existing snapshot rows are not retroactively recomputed.
- **Retirement and FIRE Forecast merged into one calculator.** The Retirement tab on `/planning` now has two modes — "Age-Based Target" (the existing 4%-rule/gap/benchmarks calculator) and "FIRE Trajectory" (the former standalone `/forecast` page's wealth-projection chart and milestones) — toggled without a page reload. `/forecast` now redirects into the trajectory mode, query params preserved, like the other consolidated planning pages. Each mode keeps its own contextually-correct starting-value default (portfolio value for the target; full net worth for the trajectory); "Expected Annual Return" is shared between both since it's the same real-world assumption either way.
- The 365-day IRS long/short-term capital-gains threshold was independently re-derived in three places (`ExportController`'s realized-gains CSV, `TaxSummaryController`'s year grouping, and the portfolio show page's lot table). `RealizedGainService::compute()` now tags each realized-gain lot with `term` ('long'/'short') once; all three read that instead.
- `DemoMode::scaleValues()` (a flat-array numeric scaler with exactly one call site) retired in favor of the already-existing `scaleAmounts()`, which does the same walk for any nested structure — one fewer near-duplicate entry point in the demo-mode scaling API.

### Flagged (not fixed — needs a product decision)

- Credit-card `CashAccount`s and `Liability` remain two unreconciled "debt with an APR" models: `Liability::monthlyInterest()` and the credit-card panel on `cash-accounts/show.blade.php` compute the identical monthly-interest formula independently, and the debt-payoff/allocator calculators only ever see `Liability` rows — a credit card tracked purely as a `CashAccount` is invisible to them. Reconciling the two (e.g. should a credit-card `CashAccount` imply a shadow `Liability`?) is a product decision, documented in code at both sites rather than silently picked.

### Fixed
- Nginx no longer caches a dead container IP for the PHP upstream after a redeploy — `fastcgi_pass` now resolves dynamically via Docker's embedded DNS instead of once at config load, eliminating post-deploy 502s.
- `Transaction::totalCost()` no longer double-counts fees paid in-asset (`fee_in_asset` transactions) — cost basis for those buys was previously overstated.

## [1.9.0] - 2026-07-12

### Added

- **Account summary email** — opt-in periodic email covering account activity, configurable per user on `/profile`. Frequency (Daily / Weekly / Monthly) and content sections (Budgeting, Investing, Net Worth, Upcoming Scheduled Transactions, Category % Changes, Warnings) are each independently multi-selectable. Sent by a new `email:send-summaries` command, scheduled daily at 06:00 (after the nightly snapshot/materialize jobs) — daily always, weekly on Mondays, monthly on the 1st.

### Changed
- Manual transaction and CSV-import validation rules consolidated into `Transaction::fieldRules()`, shared by the store/update controller actions and `TransactionCsvImportService`, so the three no longer drift independently.
- CSV importer now detects duplicate header names up front (previously a duplicate header would silently misalign every field after it) and reports accurate physical file line numbers for row errors even when blank lines were skipped.
- Forecast page's demo-mode figure masking extracted into a reusable `<x-masked-money-input>` component.
- `PortfolioSliceController` now raises a `ValidationException` for an unknown symbol instead of an ad-hoc redirect, matching every other "entity must exist" endpoint.
- `Liability::isRevolvingType()` extracted as a static helper so the escrow-clearing rule can be checked before a model is persisted.

### Fixed
- Liability scheduled payments no longer floor principal at zero when a payment doesn't cover the interest due — the balance now grows (negative amortization) instead of silently freezing, which matters now that any liability type (not just mortgages) can be scheduled.

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

## [1.8.1] - 2026-07-12

### Added
- **Cash-account-to-cash-account transfers** — a new `/cash-transfers` flow creates a linked withdrawal/deposit pair between a user's own cash accounts, shown together in transaction lists.

## [1.8.0] - 2026-07-11

### Changed
- Six standalone planning/analysis pages (cashflow, spending-trends, budget-rule, debt-payoff, allocator, emergency-fund) consolidated into tabs under `/analysis` and `/planning`; old URLs redirect with query params preserved.
- Dashboard/forecast/retirement logic extracted into `NetWorthService`, `ForecastService`, and `RetirementProjectionService`; Finnhub and CoinGecko HTTP calls moved into dedicated `FinnhubClient`/`CoinGeckoClient` classes; scheduled-transaction type strings replaced with a `ScheduledTransactionType` enum; the two cash-ledger Livewire components now share a `ManagesCashTransactionForm` trait.
- Envelope "assign" changed from incrementing to setting the month's total, with delta recording, rejection of future months, and stricter `Y-m` month parsing.
- Chart.js removed from the global JS bundle and split into per-page bundles (~79 KB gz saved on non-chart pages).

### Fixed
- Dual Alpine.js instances (the app bundle's own import racing Livewire's bundled one) were breaking all `wire:*` bindings app-wide; Livewire's bundle is now the sole Alpine instance.
- A user-submitted `mortgage_payment` schedule (system-managed only) could crash daily transaction materialization via a null cash account; now rejected.

## [1.7.0] - 2026-07-10

### Added
- Admin: action to manually mark a user's email as verified.

### Changed
- Laravel's scheduler now runs as its own `scheduler` docker-compose service (`php artisan schedule:work`) instead of depending on a host crontab, which had silently stopped running.
- Portfolio CSV import batches asset lookups instead of one query per row; `FetchBenchmarkPrices` and `PortfolioSnapshotBackfillService` batch-write via `upsert()` instead of per-row `updateOrCreate`; `BenchmarkService::all()` is now cached for an hour.

### Fixed
- Dashboard and portfolio-show pages computed portfolio value with different rules; both now go through one shared `Portfolio::summarizeHoldings()` method.

### Removed
- Watchlist feature.

## [1.6.2] - 2026-07-09

### Added
- **Pension tracking** — new pension model with full CRUD, computing accrued/projected annual benefit, present value (COLA'd real annuity, deferred to draw age), marginal value per extra year of service, and a retirement "income floor" (pension + portfolio at a safe withdrawal rate vs. target expenses). Optionally folded into dashboard net worth; included in full-backup export.
- Desktop navigation rebuilt as a collapsible left sidebar with accordion sections (Budget/Accounts/Invest/Plan) and inline per-account balances, replacing the old dropdown nav; mobile nav polished for safe-area insets and body-scroll locking while open.
- Dashboard "display options" — every dashboard section/stat tile can now be individually toggled on the profile screen (all visible by default).
- Dashboard calendar heatmap of day-over-day portfolio $/% change (GitHub-contributions style), paged month by month.
- Portfolio-snapshot backfill made resumable: when historical price fetching hits a provider rate limit, remaining work queues as a `BackfillRequest` and drains in batches via a new hourly `assets:process-backfill-queue` command (later extended to the snapshot-writing pass too, via a chunked write cursor) instead of blocking or failing the admin backfill tool outright.

## [1.5.7] - 2026-06-21

### Added
- Consolidated **"All Transactions"** ledger page showing every cash account's activity plus upcoming scheduled entries in one place, replacing the standalone Scheduled Transactions index; supports a pending/cleared status filter and separate Outflow/Inflow columns.
- Recurring/scheduled transactions gained **Quarterly** and **Yearly** cadences alongside Monthly/Weekly/Biweekly.
- Generic, configurable **CSV importer** with column-mapping presets and selectable date formats replaces the YNAB-only importer (YNAB is now just one preset).
- Envelope setting **"include in emergency fund"** — lets a non-mandatory essential (groceries, fuel, etc.) count toward the emergency-fund target without being reclassified as a "Need".

### Changed
- Emergency Fund monthly baseline now uses the larger of the 6-month historical average or the active scheduled recurring amount per envelope, so one recorded large payment isn't diluted across the window; the 50/30/20 Budget Rule page reuses the same calculation.
- Docker entrypoint installs Composer dev dependencies on every restart unless `APP_ENV=production` (previously always `--no-dev`), so tests/lint survive local container restarts without a manual reinstall.
- Business logic extracted from controllers into `AllocatorService`, `CashflowService`, `EmergencyFundService`, `SpendingTrendsService`; investment transaction types and dashboard allocation buckets moved to enums.

### Fixed
- Transaction row delete button was invisible until hover; now shown as muted gray.
- Livewire inline-edit table rows were missing `wire:key`, which could attach edit state to the wrong row after a DOM diff.

## [1.4.0] - 2026-06-16

### Added
- **50/30/20 quick calculator** on the Budget Rule page — type any monthly income and see the Necessities/Wants/Savings split instantly.
- **Enter now / skip** actions for scheduled transactions, plus pagination and text/amount filtering on the transaction list.
- **Cleared/pending transaction status** — cash transactions can be marked cleared or pending; account balances split into working/cleared/uncleared totals.
- **Income categories** — full CRUD, assignable to deposits/income, included in CSV and full-backup exports.

### Changed
- Envelope "mandatory" flag relabeled "Necessity" with clearer help text distinguishing it from the emergency-fund and wealth-building checkboxes.
- Money nav menu rebuilt as a single data-driven list shared between desktop and mobile.
- 50/30/20 "mandatory" spend now measured by envelope funding amounts rather than actual cash withdrawals, aligning it with how savings is measured.
- Widened the main content container across most pages for a more spacious layout.

### Fixed
- Backfilled portfolio snapshots could treat a manual asset as zero shares when its synthetic share count wasn't computed at save time; the backfill now derives it from the anchor-date price.

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

[1.9.0]: https://github.com/joshespi/portfolio-tracker/compare/1.2.0...HEAD
[1.2.0]: https://github.com/joshespi/portfolio-tracker/releases/tag/1.2.0
[1.1.0]: https://github.com/joshespi/portfolio-tracker/releases/tag/1.1.0
[1.0.0]: https://github.com/joshespi/portfolio-tracker/releases/tag/1.0.0
