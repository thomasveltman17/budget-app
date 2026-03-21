# Budget App

Personal finance and budgeting application built with **Laravel 12** and **FilamentPHP 5**. Designed as a single-user app to track income, expenses, budgets, savings goals, and investments — all behind a secure login.

## Tech Stack

- **Backend:** Laravel 12 (PHP 8.2+)
- **Admin Panel / UI:** FilamentPHP 5 (Livewire, Alpine.js, Tailwind CSS)
- **Database:** MySQL
- **Charts:** ApexCharts via [filament-apex-charts](https://github.com/leandrocfe/filament-apex-charts)
- **CSV Parsing:** League CSV
- **Asset Bundling:** Vite
- **Testing:** PHPUnit 11
- **Code Style:** Laravel Pint

## Features

- **Account Management** — Track checking, savings, credit card, and investment accounts
- **Transaction Tracking** — Log income and expenses with categories, notes, and review status
- **Budgeting** — Create budgets with configurable methods (50/30/20, 70/20/10, Pay Yourself First, or custom)
- **Custom Budget Periods** — Set a custom budget start day (1–31) per user
- **Category System** — Hierarchical categories with subcategories, colors, and icons
- **Category Rules** — Auto-categorize transactions based on configurable matching rules
- **AI Categorization** — Bulk-categorize uncategorized transactions using Google AI
- **CSV Import** — Import bank statement CSVs with a review step before finalizing
- **Savings Goals** — Set targets with deadlines and track progress
- **Trading212 Integration** — Sync investment portfolio value via the Trading212 API (read-only)
- **Budget Alerts** — In-app notifications at 80% and 100% budget usage
- **Monthly Summary Email** — Automated email summary sent on your budget start day
- **Onboarding Flow** — Guided setup with preset category bundles and budget suggestions

## Dashboard Widgets

- Net worth overview across all accounts
- Income vs. expense comparison for the current period
- Budget progress bars per category
- Recent transactions list
- Spending by category (pie chart)
- Income vs. expense over time (bar/line chart)

## Getting Started

### Prerequisites

- PHP 8.2+
- Composer
- Node.js & npm
- MySQL

### Installation

```bash
# Clone the repository
git clone git@github.com:thomasveltman17/budget-app.git
cd budget-app

# Install dependencies and set up the app
composer setup
```

The `composer setup` script will:
1. Install PHP dependencies
2. Copy `.env.example` to `.env` (if `.env` doesn't exist)
3. Generate an application key
4. Run database migrations
5. Install npm dependencies
6. Build front-end assets

### Configuration

Copy `.env.example` to `.env` and configure your database connection:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=budget_app
DB_USERNAME=root
DB_PASSWORD=
```

### Running the App

```bash
# Start all services (server, queue, logs, vite) concurrently
composer run dev
```

This starts:
- Laravel dev server
- Queue worker
- Laravel Pail (log viewer)
- Vite dev server (HMR)

### Running Tests

```bash
php artisan test
```

## Project Structure

```
app/
├── Enums/              # AccountType, BudgetingMethod, CategoryType, TransactionType, etc.
├── Filament/
│   ├── Pages/          # Onboarding, ImportTransactions, ProfileSettings
│   ├── Resources/      # Account, Budget, Category, CategoryRule, SavingsGoal, Transaction
│   └── Widgets/        # Dashboard widgets (charts, stats, recent transactions)
├── Jobs/               # AI categorization, monthly summary
├── Mail/               # Monthly summary email
├── Models/             # Account, Budget, Category, CategoryRule, SavingsGoal, Transaction, User
├── Observers/          # TransactionObserver (budget recalculation)
└── Services/           # Trading212, BudgetPeriod, BudgetAlert, CsvImport, Onboarding, etc.
```

## Key Design Decisions

- **Money as integers** — All monetary values are stored in cents (e.g. €12.50 = 1250) to avoid floating-point issues
- **Default currency:** EUR (€)
- **Service-oriented architecture** — Business logic lives in Service classes, keeping controllers and Filament resources thin
- **Encrypted API keys** — Trading212 API keys use Laravel's `encrypted` cast
- **Soft deletes** — Accounts, categories, transactions, and savings goals are soft-deleted

## License

[MIT](https://opensource.org/licenses/MIT)
