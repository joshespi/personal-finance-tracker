CREATE TABLE IF NOT EXISTS "migrations"(
  "id" integer primary key autoincrement not null,
  "migration" varchar not null,
  "batch" integer not null
);
CREATE TABLE IF NOT EXISTS "users"(
  "id" integer primary key autoincrement not null,
  "name" varchar not null,
  "email" varchar not null,
  "email_verified_at" datetime,
  "password" varchar not null,
  "remember_token" varchar,
  "created_at" datetime,
  "updated_at" datetime,
  "is_admin" tinyint(1) not null default '0'
);
CREATE UNIQUE INDEX "users_email_unique" on "users"("email");
CREATE TABLE IF NOT EXISTS "password_reset_tokens"(
  "email" varchar not null,
  "token" varchar not null,
  "created_at" datetime,
  primary key("email")
);
CREATE TABLE IF NOT EXISTS "sessions"(
  "id" varchar not null,
  "user_id" integer,
  "ip_address" varchar,
  "user_agent" text,
  "payload" text not null,
  "last_activity" integer not null,
  primary key("id")
);
CREATE INDEX "sessions_user_id_index" on "sessions"("user_id");
CREATE INDEX "sessions_last_activity_index" on "sessions"("last_activity");
CREATE TABLE IF NOT EXISTS "cache"(
  "key" varchar not null,
  "value" text not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_expiration_index" on "cache"("expiration");
CREATE TABLE IF NOT EXISTS "cache_locks"(
  "key" varchar not null,
  "owner" varchar not null,
  "expiration" integer not null,
  primary key("key")
);
CREATE INDEX "cache_locks_expiration_index" on "cache_locks"("expiration");
CREATE TABLE IF NOT EXISTS "jobs"(
  "id" integer primary key autoincrement not null,
  "queue" varchar not null,
  "payload" text not null,
  "attempts" integer not null,
  "reserved_at" integer,
  "available_at" integer not null,
  "created_at" integer not null
);
CREATE INDEX "jobs_queue_index" on "jobs"("queue");
CREATE TABLE IF NOT EXISTS "job_batches"(
  "id" varchar not null,
  "name" varchar not null,
  "total_jobs" integer not null,
  "pending_jobs" integer not null,
  "failed_jobs" integer not null,
  "failed_job_ids" text not null,
  "options" text,
  "cancelled_at" integer,
  "created_at" integer not null,
  "finished_at" integer,
  primary key("id")
);
CREATE TABLE IF NOT EXISTS "failed_jobs"(
  "id" integer primary key autoincrement not null,
  "uuid" varchar not null,
  "connection" text not null,
  "queue" text not null,
  "payload" text not null,
  "exception" text not null,
  "failed_at" datetime not null default CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX "failed_jobs_uuid_unique" on "failed_jobs"("uuid");
CREATE TABLE IF NOT EXISTS "portfolios"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "description" text,
  "currency" varchar not null default 'USD',
  "created_at" datetime,
  "updated_at" datetime,
  "target_stock_pct" integer not null default '0',
  "target_crypto_pct" integer not null default '0',
  "target_manual_pct" integer not null default '0',
  "target_real_estate_pct" integer not null default '0',
  "target_bond_pct" integer not null default '0',
  "is_tax_advantaged" tinyint(1) not null default '0',
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE TABLE IF NOT EXISTS "asset_prices"(
  "id" integer primary key autoincrement not null,
  "asset_id" integer not null,
  "price" numeric not null,
  "currency" varchar not null default 'USD',
  "recorded_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("asset_id") references "assets"("id") on delete cascade
);
CREATE UNIQUE INDEX "asset_prices_asset_id_recorded_at_unique" on "asset_prices"(
  "asset_id",
  "recorded_at"
);
CREATE INDEX "asset_prices_asset_id_recorded_at_index" on "asset_prices"(
  "asset_id",
  "recorded_at"
);
CREATE TABLE IF NOT EXISTS "manual_valuations"(
  "id" integer primary key autoincrement not null,
  "manual_asset_id" integer not null,
  "value" numeric not null,
  "notes" text,
  "valued_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("manual_asset_id") references "manual_assets"("id") on delete cascade
);
CREATE INDEX "manual_valuations_manual_asset_id_valued_at_index" on "manual_valuations"(
  "manual_asset_id",
  "valued_at"
);
CREATE TABLE IF NOT EXISTS "portfolio_snapshots"(
  "id" integer primary key autoincrement not null,
  "portfolio_id" integer not null,
  "cost_basis" numeric not null default '0',
  "market_value" numeric not null default '0',
  "manual_value" numeric not null default '0',
  "recorded_on" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("portfolio_id") references "portfolios"("id") on delete cascade
);
CREATE UNIQUE INDEX "portfolio_snapshots_portfolio_id_recorded_on_unique" on "portfolio_snapshots"(
  "portfolio_id",
  "recorded_on"
);
CREATE INDEX "portfolio_snapshots_portfolio_id_recorded_on_index" on "portfolio_snapshots"(
  "portfolio_id",
  "recorded_on"
);
CREATE TABLE IF NOT EXISTS "transactions"(
  "id" integer primary key autoincrement not null,
  "portfolio_id" integer not null,
  "asset_id" integer not null,
  "type" varchar not null,
  "quantity" numeric not null,
  "price_per_unit" numeric not null,
  "fees" numeric not null default('0'),
  "currency" varchar not null default('USD'),
  "notes" text,
  "transacted_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  "linked_transfer_id" integer,
  foreign key("asset_id") references assets("id") on delete restrict on update no action,
  foreign key("portfolio_id") references portfolios("id") on delete cascade on update no action,
  foreign key("linked_transfer_id") references "transactions"("id") on delete set null
);
CREATE INDEX "transactions_portfolio_id_asset_id_index" on "transactions"(
  "portfolio_id",
  "asset_id"
);
CREATE INDEX "transactions_portfolio_id_transacted_at_index" on "transactions"(
  "portfolio_id",
  "transacted_at"
);
CREATE TABLE IF NOT EXISTS "benchmark_prices"(
  "id" integer primary key autoincrement not null,
  "ticker" varchar not null,
  "recorded_on" date not null,
  "close_price" numeric not null,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE UNIQUE INDEX "benchmark_prices_ticker_recorded_on_unique" on "benchmark_prices"(
  "ticker",
  "recorded_on"
);
CREATE INDEX "benchmark_prices_ticker_recorded_on_index" on "benchmark_prices"(
  "ticker",
  "recorded_on"
);
CREATE TABLE IF NOT EXISTS "activity_logs"(
  "id" integer primary key autoincrement not null,
  "user_id" integer,
  "action" varchar not null,
  "target_type" varchar,
  "target_id" integer,
  "metadata" text,
  "ip_address" varchar,
  "user_agent" varchar,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("user_id") references "users"("id") on delete set null
);
CREATE INDEX "activity_logs_user_id_created_at_index" on "activity_logs"(
  "user_id",
  "created_at"
);
CREATE INDEX "activity_logs_action_index" on "activity_logs"("action");
CREATE TABLE IF NOT EXISTS "login_history"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "ip_address" varchar,
  "user_agent" varchar,
  "created_at" datetime not null default CURRENT_TIMESTAMP,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "login_history_user_id_created_at_index" on "login_history"(
  "user_id",
  "created_at"
);
CREATE TABLE IF NOT EXISTS "app_settings"(
  "key" varchar not null,
  "value" text,
  primary key("key")
);
CREATE TABLE IF NOT EXISTS "journal_entries"(
  "id" integer primary key autoincrement not null,
  "portfolio_id" integer not null,
  "title" varchar,
  "body" text not null,
  "entry_date" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("portfolio_id") references "portfolios"("id") on delete cascade
);
CREATE INDEX "journal_entries_portfolio_id_entry_date_index" on "journal_entries"(
  "portfolio_id",
  "entry_date"
);
CREATE TABLE IF NOT EXISTS "liabilities"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "manual_asset_id" integer,
  "name" varchar not null,
  "liability_type" varchar check("liability_type" in('mortgage', 'credit_card', 'auto_loan', 'student_loan', 'personal_loan', 'other')) not null,
  "interest_rate" numeric,
  "notes" text,
  "currency" varchar not null default 'USD',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("manual_asset_id") references "manual_assets"("id") on delete set null
);
CREATE INDEX "liabilities_user_id_liability_type_index" on "liabilities"(
  "user_id",
  "liability_type"
);
CREATE TABLE IF NOT EXISTS "liability_balances"(
  "id" integer primary key autoincrement not null,
  "liability_id" integer not null,
  "balance" numeric not null,
  "notes" text,
  "recorded_at" datetime not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("liability_id") references "liabilities"("id") on delete cascade
);
CREATE INDEX "liability_balances_liability_id_recorded_at_index" on "liability_balances"(
  "liability_id",
  "recorded_at"
);
CREATE TABLE IF NOT EXISTS "cash_accounts"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "account_type" varchar check("account_type" in('checking', 'savings', 'credit_card', 'cash', 'money_market', 'cd', 'other')) not null,
  "currency" varchar not null default 'USD',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "cash_accounts_user_id_account_type_index" on "cash_accounts"(
  "user_id",
  "account_type"
);
CREATE TABLE IF NOT EXISTS "cash_transactions"(
  "id" integer primary key autoincrement not null,
  "cash_account_id" integer not null,
  "type" varchar check("type" in('deposit', 'withdrawal')) not null,
  "amount" numeric not null,
  "description" varchar,
  "occurred_at" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("cash_account_id") references "cash_accounts"("id") on delete cascade
);
CREATE INDEX "cash_transactions_cash_account_id_occurred_at_index" on "cash_transactions"(
  "cash_account_id",
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "envelopes"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "name" varchar not null,
  "monthly_target" numeric,
  "color" varchar not null default '#6366f1',
  "sort_order" integer not null default '0',
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  "is_mandatory" tinyint(1) not null default '0',
  "is_emergency_fund" tinyint(1) not null default '0',
  "goal_amount" numeric,
  "goal_date" date,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "envelopes_user_id_sort_order_index" on "envelopes"(
  "user_id",
  "sort_order"
);
CREATE TABLE IF NOT EXISTS "envelope_transactions"(
  "id" integer primary key autoincrement not null,
  "envelope_id" integer not null,
  "type" varchar check("type" in('fund', 'spend')) not null,
  "amount" numeric not null,
  "description" varchar,
  "occurred_at" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("envelope_id") references "envelopes"("id") on delete cascade
);
CREATE INDEX "envelope_transactions_envelope_id_occurred_at_index" on "envelope_transactions"(
  "envelope_id",
  "occurred_at"
);
CREATE TABLE IF NOT EXISTS "assets"(
  "id" integer primary key autoincrement not null,
  "symbol" varchar not null,
  "name" varchar not null,
  "asset_type" varchar not null,
  "exchange" varchar,
  "coingecko_id" varchar,
  "polygon_ticker" varchar,
  "created_at" datetime,
  "updated_at" datetime
);
CREATE INDEX "assets_asset_type_index" on "assets"("asset_type");
CREATE UNIQUE INDEX "assets_symbol_unique" on "assets"("symbol");
CREATE TABLE IF NOT EXISTS "watchlist_items"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "symbol" varchar not null,
  "name" varchar,
  "asset_type" varchar not null default 'stock',
  "target_price" numeric,
  "notes" text,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references users("id") on delete cascade on update no action
);
CREATE INDEX "watchlist_items_user_id_index" on "watchlist_items"("user_id");
CREATE UNIQUE INDEX "watchlist_items_user_id_symbol_unique" on "watchlist_items"(
  "user_id",
  "symbol"
);
CREATE TABLE IF NOT EXISTS "manual_assets"(
  "id" integer primary key autoincrement not null,
  "portfolio_id" integer not null,
  "name" varchar not null,
  "description" text,
  "asset_class" varchar not null,
  "currency" varchar not null default('USD'),
  "created_at" datetime,
  "updated_at" datetime,
  "cost_basis" numeric,
  "tracking_method" varchar not null default 'static',
  "proxy_asset_id" integer,
  "anchor_value" numeric,
  "anchor_date" date,
  "anchor_synthetic_shares" numeric,
  foreign key("portfolio_id") references portfolios("id") on delete cascade on update no action,
  foreign key("proxy_asset_id") references "assets"("id") on delete set null
);
CREATE TABLE IF NOT EXISTS "scheduled_transactions"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "description" varchar not null,
  "amount" numeric not null,
  "type" varchar not null,
  "recurrence" varchar not null,
  "next_due_at" date not null,
  "envelope_id" integer,
  "cash_account_id" integer,
  "is_active" tinyint(1) not null default '1',
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade,
  foreign key("envelope_id") references "envelopes"("id") on delete set null,
  foreign key("cash_account_id") references "cash_accounts"("id") on delete set null
);
CREATE INDEX "scheduled_transactions_user_id_is_active_next_due_at_index" on "scheduled_transactions"(
  "user_id",
  "is_active",
  "next_due_at"
);
CREATE TABLE IF NOT EXISTS "income_entries"(
  "id" integer primary key autoincrement not null,
  "user_id" integer not null,
  "amount" numeric not null,
  "description" varchar,
  "occurred_at" date not null,
  "created_at" datetime,
  "updated_at" datetime,
  foreign key("user_id") references "users"("id") on delete cascade
);
CREATE INDEX "income_entries_user_id_occurred_at_index" on "income_entries"(
  "user_id",
  "occurred_at"
);

INSERT INTO migrations VALUES(1,'0001_01_01_000000_create_users_table',1);
INSERT INTO migrations VALUES(2,'0001_01_01_000001_create_cache_table',1);
INSERT INTO migrations VALUES(3,'0001_01_01_000002_create_jobs_table',1);
INSERT INTO migrations VALUES(4,'2026_04_04_180847_create_assets_table',2);
INSERT INTO migrations VALUES(5,'2026_04_04_180847_create_portfolios_table',2);
INSERT INTO migrations VALUES(6,'2026_04_04_180848_create_asset_prices_table',2);
INSERT INTO migrations VALUES(7,'2026_04_04_180848_create_manual_assets_table',2);
INSERT INTO migrations VALUES(8,'2026_04_04_180848_create_transactions_table',2);
INSERT INTO migrations VALUES(9,'2026_04_04_180849_create_manual_valuations_table',2);
INSERT INTO migrations VALUES(10,'2026_04_05_002727_create_portfolio_snapshots_table',3);
INSERT INTO migrations VALUES(11,'2026_04_07_000000_add_is_admin_to_users_table',4);
INSERT INTO migrations VALUES(12,'2026_04_23_000000_add_linked_transfer_id_to_transactions',4);
INSERT INTO migrations VALUES(13,'2026_04_25_000001_create_benchmark_prices_table',4);
INSERT INTO migrations VALUES(14,'2026_04_25_000002_create_watchlist_items_table',4);
INSERT INTO migrations VALUES(15,'2026_04_25_000003_add_target_allocations_to_portfolios',4);
INSERT INTO migrations VALUES(16,'2026_04_25_000004_create_activity_logs_table',4);
INSERT INTO migrations VALUES(17,'2026_04_25_000005_create_login_history_table',4);
INSERT INTO migrations VALUES(18,'2026_04_25_000006_create_app_settings_table',4);
INSERT INTO migrations VALUES(19,'2026_04_26_024759_create_journal_entries_table',4);
INSERT INTO migrations VALUES(20,'2026_04_26_120000_create_liabilities_table',4);
INSERT INTO migrations VALUES(21,'2026_04_26_120001_create_liability_balances_table',4);
INSERT INTO migrations VALUES(22,'2026_04_26_140000_create_cash_accounts_table',4);
INSERT INTO migrations VALUES(23,'2026_04_26_140001_create_cash_transactions_table',4);
INSERT INTO migrations VALUES(24,'2026_04_26_160000_create_envelopes_table',4);
INSERT INTO migrations VALUES(25,'2026_04_26_160001_create_envelope_transactions_table',4);
INSERT INTO migrations VALUES(26,'2026_05_03_120000_add_cost_basis_to_manual_assets_table',4);
INSERT INTO migrations VALUES(27,'2026_05_03_130000_add_real_estate_to_asset_type_enums',4);
INSERT INTO migrations VALUES(28,'2026_05_03_130001_add_target_real_estate_pct_to_portfolios',4);
INSERT INTO migrations VALUES(29,'2026_05_05_000001_add_bond_to_asset_type_enums',4);
INSERT INTO migrations VALUES(30,'2026_05_05_000002_add_target_bond_pct_to_portfolios',4);
INSERT INTO migrations VALUES(31,'2026_05_05_100000_add_proxy_tracking_to_manual_assets',4);
INSERT INTO migrations VALUES(32,'2026_05_05_110000_add_fund_classes_to_manual_assets_asset_class',4);
INSERT INTO migrations VALUES(33,'2026_05_06_000001_create_scheduled_transactions_table',4);
INSERT INTO migrations VALUES(34,'2026_05_06_000002_add_index_to_scheduled_transactions_table',4);
INSERT INTO migrations VALUES(35,'2026_05_09_000001_add_emergency_fund_flags_to_envelopes',4);
INSERT INTO migrations VALUES(36,'2026_05_09_000002_create_income_entries_table',4);
INSERT INTO migrations VALUES(37,'2026_05_10_025355_add_is_tax_advantaged_to_portfolios',4);
INSERT INTO migrations VALUES(38,'2026_05_10_100000_add_goal_to_envelopes',4);
