# Refactor plan: feature toggles + invest-from-envelope

Source: `/espi-review` run 2026-11-22 on the uncommitted per-user feature toggles and "Invest from envelope" work. Each section is standalone.

## 1. Model envelope investing as one linked cash + portfolio movement

**Why:** `EnvelopeInvestmentController` re-implements the buy flow from `TransactionController::store` (closed-portfolio check, symbol normalisation, `findOrCreateBySymbol`, `ActivityLog`) and hand-builds the matching cash withdrawal. Both legs carry `envelope_id` but nothing links them, so editing or deleting one leg in the portfolio or cash UI leaves the other behind.

**Where:** `src/app/Http/Controllers/EnvelopeInvestmentController.php`, `TransactionController::store`, `Transaction` (`envelope_id`), `Envelope::investments()/investedValue()`, migration `2026_11_22_000002_add_envelope_id_to_transactions.php`. Existing pattern to copy: `linked_transfer_id`, `CashTransferController`, `PortfolioTransferController`.

**Proposed approach:** Either a cash-to-portfolio transfer that owns both legs and their deletion, or extract the buy-recording logic into a service that both controllers call. Decide how deleting one leg should affect the other.

**Risks / open questions:** Existing rows created with the current two-leg shape need a backfill or a compatible read path. Decide what deleting a buy does to the envelope balance.

**Suggested scope:** In: shared buy service, link between legs, delete cascade. Out: the Invest form UI.

## 2. Gate Livewire ledgers under the Investing feature

**Why:** `/transactions` is behind `feature:investing`, but the Livewire components (`TransactionList`, `AllTransactions`) receive update requests that don't pass through route middleware, so a user with Investing off can still hit their write actions.

**Where:** `src/app/Livewire/TransactionList.php`, `AllTransactions.php`, shared `Concerns\ManagesCashTransactionForm`. Check which ledger (cash vs portfolio) each serves first: cash ledgers belong under Cash, not Investing.

**Proposed approach:** Gate in `mount()` or a shared trait, or register Livewire persistent middleware.

**Risks / open questions:** Confirm which module each component belongs to before gating. Needs a test per component.

**Suggested scope:** In: both components + tests. Out: route changes.

## 3. Put "counted module" gating in `User`

**Why:** `hasFeature(Investing) ? latestPortfolioValue() : 0` and the Cash equivalent are re-derived in `ForecastService`, `PensionController`, `PlanningController::retirement` and `NetWorthService`. A missed call site leaks hidden-module data.

**Where:** `ForecastService::computeDefaults`, `PensionController::portfolioBase`, `PlanningController` (~L120), `NetWorthService::compute`.

**Proposed approach:** `User::countedPortfolioValue()`, `countedCash()`, `countedDebt()` that apply the gate internally; call sites keep their shape.

**Risks / open questions:** CLAUDE.md currently documents per-call-site gating; update it. `NetWorthService` needs the portfolio collection, not just a number.

**Suggested scope:** In: the helpers + the 4 call sites. Out: view-level gating.

## 4. Let `Feature` (or a registry) own the feature-to-UI mapping

**Why:** Feature-to-UI mapping lives in many hand-written places: `DashboardWidget::feature()`, `EmailSummarySection::feature()`, `PlanningController::TAB_FEATURES`, nav `show` flags, quick-add tabs, dashboard welcome links, export cards, profile sections. Adding a module touches ~8 Blade files and nothing tests for a miss.

**Where:** `layouts/navigation.blade.php`, `dashboard.blade.php`, `export/index.blade.php`, `profile/edit.blade.php`, `planning.blade.php`.

**Proposed approach:** Give `Feature` (or a `PlanningTab` enum) the nav items, welcome actions, export cards and quick-add tabs; views filter by `enabledFeatures()`.

**Risks / open questions:** Blade views read better with literal markup; avoid over-abstracting. Start with planning tabs + nav.

**Suggested scope:** In: nav + planning tabs. Out: welcome/export cards (follow-up).

## 5. Shared "visible for user" predicate on widget/section enums

**Why:** `User::showsWidget` / `wantsEmailSummarySection` and the two profile save paths (`updateDisplay`, `updateEmailSummary`) each repeat "string → enum → `feature()` → `hasFeature()`", and each hand-rolls "keep the saved preference for hidden-module items" with different mechanics.

**Where:** `User.php`, `ProfileController::updateDisplay/updateEmailSummary`, `profile/partials/dashboard-display-form.blade.php`, `email-summary-form.blade.php`, `DashboardWidget.php`, `EmailSummarySection.php`.

**Proposed approach:** `isVisibleFor(User)` on both enums and one `preserveHidden($saved, $submitted, $isHidden)` helper.

**Risks / open questions:** `DashboardWidget::feature()` has a `default => null` arm that silently skips gating for new widgets; consider an exhaustive match.

**Suggested scope:** In: predicate + preserve helper. Out: new widgets.

## 6. Reuse the buy form instead of a third copy

**Why:** The Invest form in `envelopes/show.blade.php` is a ~70-line third copy of the quick-add "Log Buy" and `transactions/create` fields, with raw `<select>`s and a `!w-32` override.

**Where:** `envelopes/show.blade.php` (~L176-245), `layouts/navigation.blade.php` (Log Buy tab), `transactions/create.blade.php`.

**Proposed approach:** Extract a `record-buy-fields` partial/component using `x-select-input`; note CLAUDE.md lists a pre-existing missing-label bug in `record-transaction-form`.

**Suggested scope:** In: shared fields partial. Out: controller changes.
