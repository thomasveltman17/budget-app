<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\BudgetingMethod;
use App\Enums\CategoryType;
use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class OnboardingService
{
    // ──────────────────────────────────────────────
    //  Category Presets
    // ──────────────────────────────────────────────

    /**
     * Simple flat category list for quick setup.
     *
     * @return array<string, array{name: string, type: string, color: string, icon: string, group: string}>
     */
    public function getSimpleCategories(): array
    {
        return [
            'housing'       => ['name' => 'Housing',       'type' => 'expense', 'color' => '#6366f1', 'icon' => 'heroicon-o-home',            'group' => 'needs'],
            'food'          => ['name' => 'Food',          'type' => 'expense', 'color' => '#f59e0b', 'icon' => 'heroicon-o-shopping-bag',    'group' => 'needs'],
            'transport'     => ['name' => 'Transport',     'type' => 'expense', 'color' => '#3b82f6', 'icon' => 'heroicon-o-truck',           'group' => 'needs'],
            'health'        => ['name' => 'Health',        'type' => 'expense', 'color' => '#ef4444', 'icon' => 'heroicon-o-heart',           'group' => 'needs'],
            'entertainment' => ['name' => 'Entertainment', 'type' => 'expense', 'color' => '#a855f7', 'icon' => 'heroicon-o-film',            'group' => 'wants'],
            'subscriptions' => ['name' => 'Subscriptions', 'type' => 'expense', 'color' => '#ec4899', 'icon' => 'heroicon-o-credit-card',    'group' => 'wants'],
            'salary'        => ['name' => 'Salary',        'type' => 'income',  'color' => '#22c55e', 'icon' => 'heroicon-o-banknotes',       'group' => 'income'],
            'other_income'  => ['name' => 'Other Income',  'type' => 'income',  'color' => '#14b8a6', 'icon' => 'heroicon-o-currency-euro',   'group' => 'income'],
        ];
    }

    /**
     * Detailed category list with parent/child hierarchy.
     *
     * @return array<string, array{name: string, type: string, color: string, icon: string, group: string, children?: array<string, array{name: string, group: string}>}>
     */
    public function getDetailedCategories(): array
    {
        return [
            'housing' => [
                'name' => 'Housing', 'type' => 'expense', 'color' => '#6366f1', 'icon' => 'heroicon-o-home', 'group' => 'needs',
                'children' => [
                    'rent_mortgage' => ['name' => 'Rent / Mortgage', 'group' => 'needs'],
                    'utilities'     => ['name' => 'Utilities',       'group' => 'needs'],
                    'insurance'     => ['name' => 'Insurance',       'group' => 'needs'],
                ],
            ],
            'food' => [
                'name' => 'Food', 'type' => 'expense', 'color' => '#f59e0b', 'icon' => 'heroicon-o-shopping-bag', 'group' => 'needs',
                'children' => [
                    'groceries'   => ['name' => 'Groceries',   'group' => 'needs'],
                    'restaurants' => ['name' => 'Restaurants',  'group' => 'wants'],
                    'coffee'      => ['name' => 'Coffee',       'group' => 'wants'],
                ],
            ],
            'transport' => [
                'name' => 'Transport', 'type' => 'expense', 'color' => '#3b82f6', 'icon' => 'heroicon-o-truck', 'group' => 'needs',
                'children' => [
                    'fuel'             => ['name' => 'Fuel',              'group' => 'needs'],
                    'public_transport' => ['name' => 'Public Transport',  'group' => 'needs'],
                    'car_maintenance'  => ['name' => 'Car Maintenance',   'group' => 'needs'],
                ],
            ],
            'health' => [
                'name' => 'Health', 'type' => 'expense', 'color' => '#ef4444', 'icon' => 'heroicon-o-heart', 'group' => 'needs',
                'children' => [
                    'medical'  => ['name' => 'Medical',   'group' => 'needs'],
                    'pharmacy' => ['name' => 'Pharmacy',  'group' => 'needs'],
                    'fitness'  => ['name' => 'Fitness',   'group' => 'wants'],
                ],
            ],
            'entertainment' => [
                'name' => 'Entertainment', 'type' => 'expense', 'color' => '#a855f7', 'icon' => 'heroicon-o-film', 'group' => 'wants',
                'children' => [
                    'streaming'  => ['name' => 'Streaming',  'group' => 'wants'],
                    'hobbies'    => ['name' => 'Hobbies',    'group' => 'wants'],
                    'going_out'  => ['name' => 'Going Out',  'group' => 'wants'],
                ],
            ],
            'shopping' => [
                'name' => 'Shopping', 'type' => 'expense', 'color' => '#ec4899', 'icon' => 'heroicon-o-shopping-cart', 'group' => 'wants',
                'children' => [
                    'clothing'    => ['name' => 'Clothing',     'group' => 'wants'],
                    'electronics' => ['name' => 'Electronics',  'group' => 'wants'],
                    'gifts'       => ['name' => 'Gifts',        'group' => 'wants'],
                ],
            ],
            'savings' => [
                'name' => 'Savings', 'type' => 'expense', 'color' => '#0ea5e9', 'icon' => 'heroicon-o-shield-check', 'group' => 'savings',
                'children' => [
                    'emergency_fund' => ['name' => 'Emergency Fund', 'group' => 'savings'],
                    'investments'    => ['name' => 'Investments',    'group' => 'savings'],
                ],
            ],
            'salary'       => ['name' => 'Salary',       'type' => 'income', 'color' => '#22c55e', 'icon' => 'heroicon-o-banknotes',     'group' => 'income'],
            'freelance'    => ['name' => 'Freelance',    'type' => 'income', 'color' => '#14b8a6', 'icon' => 'heroicon-o-briefcase',     'group' => 'income'],
            'other_income' => ['name' => 'Other Income', 'type' => 'income', 'color' => '#84cc16', 'icon' => 'heroicon-o-currency-euro', 'group' => 'income'],
        ];
    }

    /**
     * Get category options for the CheckboxList in the wizard.
     *
     * @return array<string, string>
     */
    public function getCategoryOptions(string $bundle): array
    {
        $categories = $bundle === 'detailed'
            ? $this->getDetailedCategories()
            : $this->getSimpleCategories();

        $options = [];

        foreach ($categories as $key => $cat) {
            $label = $cat['name'];
            if (isset($cat['children'])) {
                $childNames = collect($cat['children'])->pluck('name')->implode(', ');
                $label .= " ({$childNames})";
            }
            $options[$key] = $label;
        }

        return $options;
    }

    // ──────────────────────────────────────────────
    //  Budget Computation
    // ──────────────────────────────────────────────

    /**
     * Compute budget suggestions based on the chosen method, income, and selected categories.
     *
     * @param  array<string>  $selectedCategoryKeys
     * @param  array{needs: int, wants: int, savings: int}|null  $customPcts
     * @return array<int, array{category_key: string, name: string, group: string, amount: float}>
     */
    public function computeBudgetSuggestions(
        string $method,
        float $monthlyIncome,
        array $selectedCategoryKeys,
        string $bundle,
        ?array $customPcts = null,
    ): array {
        $splits = $this->getGroupSplits($method, $customPcts);

        $categories = $bundle === 'detailed'
            ? $this->getDetailedCategories()
            : $this->getSimpleCategories();

        // Only include expense categories that were selected
        $expenseCategories = collect($categories)
            ->filter(fn ($cat, $key) => in_array($key, $selectedCategoryKeys) && $cat['type'] === 'expense');

        // Group by budget group (needs, wants, savings)
        $grouped = $expenseCategories->groupBy('group');

        $suggestions = [];

        foreach ($grouped as $group => $cats) {
            $groupPct = $splits[$group] ?? 0;
            $groupAmount = $monthlyIncome * ($groupPct / 100);
            $count = $cats->count();

            if ($count === 0) {
                continue;
            }

            // Distribute equally within each group
            $perCategory = round($groupAmount / $count, 2);

            foreach ($cats as $key => $cat) {
                $suggestions[] = [
                    'category_key' => $key,
                    'name'         => $cat['name'],
                    'group'        => $group,
                    'amount'       => $perCategory,
                ];
            }
        }

        return $suggestions;
    }

    /**
     * Get the percentage splits for the chosen budgeting method.
     *
     * @return array{needs: int, wants: int, savings: int}
     */
    protected function getGroupSplits(string $method, ?array $customPcts = null): array
    {
        return match ($method) {
            'fifty_thirty_twenty' => ['needs' => 50, 'wants' => 30, 'savings' => 20],
            'seventy_twenty_ten'  => ['needs' => 70, 'wants' => 20, 'savings' => 10],
            'pay_yourself_first'  => ['needs' => 50, 'wants' => 30, 'savings' => 20],
            'custom'              => [
                'needs'   => (int) ($customPcts['needs'] ?? 50),
                'wants'   => (int) ($customPcts['wants'] ?? 30),
                'savings' => (int) ($customPcts['savings'] ?? 20),
            ],
            default => ['needs' => 50, 'wants' => 30, 'savings' => 20],
        };
    }

    // ──────────────────────────────────────────────
    //  Completion Handler
    // ──────────────────────────────────────────────

    /**
     * Create all records and finalise onboarding.
     */
    public function complete(User $user, array $data): void
    {
        DB::transaction(function () use ($user, $data) {
            // 1. Update user preferences
            $user->update([
                'monthly_income'       => (int) round(((float) $data['monthly_income']) * 100),
                'budget_start_day'     => (int) $data['budget_start_day'],
                'budgeting_method'     => $data['budgeting_method'],
                'onboarding_completed' => true,
            ]);

            // 2. Create categories (returns map of key → ['id', 'group'])
            $categoryMap = $this->createCategories($data);

            // 3. Create percentage-based budget groups and attach categories
            $this->createBudgetGroups($data, $categoryMap);

            // 4. Create accounts
            $this->createAccounts($data);
        });
    }

    /**
     * Create category records from the wizard data.
     *
     * @return array<string, array{id: int, group: string}>  Map of category key → {id, group}
     */
    protected function createCategories(array $data): array
    {
        $bundle     = $data['category_bundle'] ?? 'simple';
        $selected   = $data['selected_categories'] ?? [];
        $categories = $bundle === 'detailed'
            ? $this->getDetailedCategories()
            : $this->getSimpleCategories();

        $map = [];

        foreach ($selected as $key) {
            if (! isset($categories[$key])) {
                continue;
            }

            $cat = $categories[$key];

            $parent = Category::create([
                'name'  => $cat['name'],
                'type'  => $cat['type'],
                'color' => $cat['color'],
                'icon'  => $cat['icon'],
            ]);

            $map[$key] = ['id' => $parent->id, 'group' => $cat['group']];

            // Create children if present (detailed bundle)
            if (isset($cat['children'])) {
                foreach ($cat['children'] as $childKey => $child) {
                    $childModel = Category::create([
                        'name'      => $child['name'],
                        'type'      => $cat['type'],
                        'parent_id' => $parent->id,
                        'color'     => $cat['color'],
                        'icon'      => $cat['icon'],
                    ]);

                    // Children carry their own group (may differ from parent)
                    $map[$childKey] = ['id' => $childModel->id, 'group' => $child['group']];
                }
            }
        }

        // Create custom categories (no budget group — user assigns later)
        foreach ($data['custom_categories'] ?? [] as $custom) {
            if (empty($custom['name'])) {
                continue;
            }

            $customCat = Category::create([
                'name'  => $custom['name'],
                'type'  => $custom['type'] ?? 'expense',
                'color' => '#64748b',
                'icon'  => 'heroicon-o-tag',
            ]);

            $map['custom_' . $customCat->id] = ['id' => $customCat->id, 'group' => 'none'];
        }

        return $map;
    }

    /**
     * Create persistent percentage-based budget groups and attach the right categories.
     *
     * @param  array<string, array{id: int, group: string}>  $categoryMap
     */
    protected function createBudgetGroups(array $data, array $categoryMap): void
    {
        $method = $data['budgeting_method'] ?? 'fifty_thirty_twenty';

        $customPcts = $method === 'custom' ? [
            'needs'   => (int) ($data['custom_needs_pct'] ?? 50),
            'wants'   => (int) ($data['custom_wants_pct'] ?? 30),
            'savings' => (int) ($data['custom_savings_pct'] ?? 20),
        ] : null;

        $splits = $this->getGroupSplits($method, $customPcts);

        $groupNames = [
            'needs'   => 'Needs',
            'wants'   => 'Wants',
            'savings' => 'Savings',
        ];

        // Index category IDs by group
        $byGroup = ['needs' => [], 'wants' => [], 'savings' => []];

        foreach ($categoryMap as $entry) {
            $group = $entry['group'] ?? 'none';
            if (isset($byGroup[$group])) {
                $byGroup[$group][] = $entry['id'];
            }
        }

        foreach ($splits as $group => $percentage) {
            $categoryIds = $byGroup[$group] ?? [];

            // Only create a budget group if there are categories or a non-zero percentage
            if ($percentage <= 0) {
                continue;
            }

            $budget = Budget::create([
                'name'       => $groupNames[$group] ?? ucfirst($group),
                'percentage' => $percentage,
                'is_active'  => true,
            ]);

            if (! empty($categoryIds)) {
                Category::whereIn('id', $categoryIds)->update(['budget_id' => $budget->id]);
            }
        }
    }

    /**
     * Create account records from the wizard data.
     */
    protected function createAccounts(array $data): void
    {
        foreach ($data['accounts'] ?? [] as $account) {
            if (empty($account['name'])) {
                continue;
            }

            $balanceCents = (int) round(((float) ($account['balance'] ?? 0)) * 100);

            Account::create([
                'name'      => $account['name'],
                'type'      => $account['type'],
                'balance'   => $balanceCents,
                'broker'    => ($account['type'] === 'investment') ? 'trading212' : null,
                'api_key'   => $account['api_key'] ?? null,
                'currency'  => 'EUR',
                'is_active' => true,
            ]);
        }
    }
}
