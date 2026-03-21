# Budget App — Wiki

## Overview

Personal finance and budgeting app. Single-user (one login account, no multi-tenancy).

- **Framework:** Laravel 12
- **Admin Panel:** FilamentPHP 5
- **Database:** MySQL (SQLite in-memory for tests)
- **Language:** PHP 8.2+
- **External API:** Trading212 Public API (read-only, investment accounts)

---

## Key Conventions

### Money
- All monetary values are stored as **integers in cents** (e.g. €12.50 = 1250)
- Always use `bigInteger` columns for monetary fields in migrations
- Display: divide by 100, format to 2 decimal places
- Default currency: **EUR (€)**

### Naming
- Singular model names: `Transaction`, `Account`, `Category`, `Budget`
- Foreign keys follow Laravel conventions: `account_id`, `category_id`, etc.
- Service classes live in `App\Services\`
- Enums live in `App\Enums\`

### Architecture
- **Thin controllers, fat services** — all business logic goes in Service classes
- All Trading212 API calls go through `App\Services\Trading212Service`
- All budget period date logic goes through `App\Services\BudgetPeriodService`

---

## Database Schema

### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| email | string | unique |
| password | string | hashed |
| budget_start_day | tinyint | 1–31, default 1 |
| monthly_income | bigint | cents, default 0 |
| budgeting_method | enum | see BudgetingMethod enum |
| onboarding_completed | boolean | default false |
| remember_token | string | nullable |
| created_at / updated_at | timestamp | |

### `accounts`
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| type | enum | checking, savings, credit_card, investment |
| balance | bigint | cents |
| broker | string | nullable — e.g. 'trading212' |
| api_key | text | nullable, stored **encrypted** |
| currency | string(3) | default 'EUR' |
| is_active | boolean | default true |
| deleted_at | timestamp | soft deletes |

### `categories`
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| type | enum | income, expense |
| parent_id | bigint | nullable FK → categories (nullOnDelete) |
| color | string(7) | hex color e.g. #FF5733 |
| icon | string | icon name |
| budget_percentage | int | nullable, % of income allocated |
| deleted_at | timestamp | soft deletes |

### `transactions`
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| account_id | bigint | FK → accounts (cascadeOnDelete) |
| category_id | bigint | nullable FK → categories (nullOnDelete) |
| amount | bigint | cents |
| type | enum | income, expense |
| description | string | |
| date | date | |
| is_reviewed | boolean | default false (used in CSV import flow) |
| notes | text | nullable |
| deleted_at | timestamp | soft deletes |

### `budgets`
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| category_id | bigint | nullable FK → categories (nullOnDelete) |
| amount | bigint | cents — allocated budget |
| period_year | int | |
| period_month | int | |
| spent | bigint | cents — cached, recalculated on transaction save |

### `savings_goals`
| Column | Type | Notes |
|---|---|---|
| id | bigint | PK |
| name | string | |
| target_amount | bigint | cents |
| current_amount | bigint | cents, default 0 |
| deadline | date | |
| notes | text | nullable |
| is_completed | boolean | default false |
| deleted_at | timestamp | soft deletes |

**Soft deletes on:** `accounts`, `categories`, `transactions`, `savings_goals`
**No soft deletes on:** `budgets`

---

## Models

### `App\Models\User`
- Casts: `budget_start_day` → int, `monthly_income` → int, `budgeting_method` → `BudgetingMethod` enum, `onboarding_completed` → bool

### `App\Models\Account`
- Casts: `type` → `AccountType` enum, `balance` → int, `api_key` → `encrypted`, `is_active` → bool
- Relations: `transactions()` HasMany

### `App\Models\Category`
- Casts: `type` → `CategoryType` enum, `budget_percentage` → int
- Relations: `parent()` BelongsTo, `children()` HasMany, `transactions()` HasMany, `budgets()` HasMany
- Helpers: `isParent(): bool`, `isSubcategory(): bool`

### `App\Models\Transaction`
- Casts: `amount` → int, `type` → `TransactionType` enum, `date` → date, `is_reviewed` → bool
- Relations: `account()` BelongsTo, `category()` BelongsTo
- Scopes: `scopeReviewed()`, `scopeUnreviewed()`, `scopeForPeriod(int $year, int $month)`

### `App\Models\Budget`
- Casts: `amount` → int, `spent` → int, `period_year` → int, `period_month` → int
- Relations: `category()` BelongsTo
- Scopes: `scopeForPeriod(int $year, int $month)`, `scopeForCurrentPeriod()`

### `App\Models\SavingsGoal`
- Casts: `target_amount` → int, `current_amount` → int, `deadline` → date, `is_completed` → bool

---

## Enums

| Enum | Values |
|---|---|
| `App\Enums\AccountType` | `checking`, `savings`, `credit_card`, `investment` |
| `App\Enums\BudgetingMethod` | `fifty_thirty_twenty`, `seventy_twenty_ten`, `pay_yourself_first`, `custom` |
| `App\Enums\CategoryType` | `income`, `expense` |
| `App\Enums\TransactionType` | `income`, `expense` |

---

## Services

### `App\Services\BudgetPeriodService`

Calculates budget period date ranges based on a user's `budget_start_day`.

**Edge case:** if `budget_start_day` is 29, 30, or 31 and the month is shorter, falls back to the last day of that month (e.g. day 31 in February → Feb 28/29).

```php
// Get the start of the current budget period for a user
$service->getCurrentPeriodStart(User $user): Carbon

// Get the end of the current budget period (day before next period starts)
$service->getCurrentPeriodEnd(User $user): Carbon

// Get the start of a specific month's period
$service->getPeriodStart(User $user, int $year, int $month): Carbon
```

Uses `Carbon::today()` internally — tests can control "now" via `Carbon::setTestNow()`.
Uses `subMonthNoOverflow()` / `addMonthNoOverflow()` to avoid Carbon date overflow at month boundaries.

---

### `App\Services\Trading212Service`

Fetches and syncs portfolio value from the Trading212 API for an investment account.

```php
$service = new Trading212Service(Account $account);

// Fetch current total portfolio value in cents
$service->getPortfolioValue(): int

// Fetch value and persist to account->balance, with Log::info
$service->syncPortfolioValue(Account $account): void
```

- **API endpoint:** `GET https://live.trading212.com/api/v0/equity/account/cash`
- **Auth:** HTTP Basic Auth — username = `account->api_key`, password = `''`
- **Timeout:** 10 seconds
- **Response field used:** `total` (full account value: cash + invested)
- **Conversion:** `(int) round($total * 100)` → stored as cents
- **On HTTP error (4xx/5xx):** throws `\RuntimeException` with status code + body
- **On network error:** `Illuminate\Http\Client\ConnectionException` propagates naturally
- The `api_key` is automatically decrypted by Eloquent's `encrypted` cast on `Account`

---

## Budget Periods

Users set a `budget_start_day` (1–31) in their profile. Budget periods are not calendar months — they run from `budget_start_day` of one month to `budget_start_day - 1` of the next.

**Example (start day = 15):**
- Period: March 15 → April 14
- Next period: April 15 → May 14

**Month-length edge cases:**
- Start day 31, January → Jan 31 ✓
- Start day 31, April (30 days) → Apr 30
- Start day 31, February 2025 (28 days) → Feb 28
- Start day 31, February 2024 (leap, 29 days) → Feb 29

---

## Testing

- **Framework:** PHPUnit 11.5.50
- **Test DB:** SQLite in-memory (configured in `phpunit.xml`)
- **Mocking:** Mockery 1.6
- **Unit tests:** `tests/Unit/` — extend `Tests\TestCase`
- **Feature tests:** `tests/Feature/` — extend `Tests\TestCase`

Run all tests:
```bash
php artisan test
```

Run a specific test file:
```bash
php artisan test tests/Unit/BudgetPeriodServiceTest.php
```

### Existing Tests

**`tests/Unit/BudgetPeriodServiceTest.php`** — 17 tests covering:
- `getPeriodStart` with normal days (1, 15, 31)
- Clamping for short months (April/30 days, February leap/non-leap)
- `getCurrentPeriodStart` when today is before/on/after start day
- `getCurrentPeriodStart` wrapping to December when in January
- `getCurrentPeriodEnd` across month/year boundaries

---

## Planned Features

| Feature | Status |
|---|---|
| Migrations & models | ✅ Done |
| Services: BudgetPeriodService | ✅ Done |
| Services: Trading212Service | ✅ Done |
| Filament Resources (CRUD) | Not started |
| Onboarding flow | Not started |
| Dashboard widgets | Not started |
| CSV import with review step | Not started |
| Budget alert notifications (80% / 100%) | Not started |
| Monthly summary email | Not started |
| Savings goals tracking | Not started |
| Services: BudgetAlertService | Not started |
| Services: CsvImportService | Not started |
| Services: OnboardingService | Not started |

---

## Planned Filament Resources

1. `AccountResource`
2. `CategoryResource` (with subcategory support)
3. `TransactionResource` (with bulk review action for CSV imports)
4. `BudgetResource`
5. `SavingsGoalResource`

---

## Planned Dashboard Widgets

| Widget | Type |
|---|---|
| `NetWorthWidget` | StatsOverview — total balance across all accounts |
| `MonthlyIncomeExpenseWidget` | StatsOverview — income vs expenses for current period |
| `BudgetProgressWidget` | Progress bars per category |
| `RecentTransactionsWidget` | Last 5–10 transactions |
| `Trading212PortfolioWidget` | Investment account value |
| `SpendingByCategory` | ApexCharts pie chart (`leandrocfe/filament-apex-charts`) |
| `IncomeVsExpenseChart` | ApexCharts line/bar chart over time |
| `NetWorthGrowthChart` | ApexCharts line chart |

---

## Scheduled Jobs (Planned)

| Schedule | Job |
|---|---|
| Daily | Sync Trading212 portfolio value via `Trading212Service` |
| Monthly (on `budget_start_day`) | Reset budget period, send monthly summary email |

---

## Security Notes

- Trading212 `api_key` is **never stored in plain text** — Laravel's `encrypted` cast encrypts at rest using `APP_KEY`
- Trading212 API is read-only (no write operations possible)
- Budget alerts fire **in-app only** (Filament notifications) — no push or SMS
- Monthly summary is delivered by **email** (Laravel Mail + queue)
