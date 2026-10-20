<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Services\BudgetAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BudgetTripSpendingTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    private Budget $budget;

    private Category $category;

    private Carbon $periodStart;

    private Carbon $periodEnd;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create(['monthly_income' => 300000]));

        $this->mock(BudgetAlertService::class)
            ->shouldReceive('checkAllBudgets');

        $this->account = Account::factory()->create();
        $this->budget = Budget::create(['name' => 'Wants', 'percentage' => 30, 'is_active' => true]);
        $this->category = Category::factory()->create([
            'name' => 'Restaurants',
            'type' => 'expense',
            'budget_id' => $this->budget->id,
        ]);

        $this->periodStart = Carbon::parse('2026-09-01');
        $this->periodEnd = Carbon::parse('2026-09-30');
    }

    private function expense(int $amount, ?int $share = null, array $attributes = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'account_id' => $this->account->id,
            'category_id' => $this->category->id,
            'amount' => $amount,
            'share_amount' => $share,
            'type' => 'expense',
            'date' => '2026-09-12',
        ], $attributes));
    }

    private function spent(): int
    {
        return $this->budget->spentInPeriod($this->periodStart, $this->periodEnd);
    }

    public function test_an_ordinary_expense_counts_in_full(): void
    {
        $this->expense(4500);

        $this->assertSame(4500, $this->spent());
    }

    public function test_a_fronted_group_expense_only_costs_the_budget_your_share(): void
    {
        $trip = Trip::factory()->create(['counts_toward_budget' => true]);

        $this->expense(12000, 3000, ['trip_id' => $trip->id]);

        $this->assertSame(3000, $this->spent());
    }

    public function test_settlement_transfers_never_touch_the_budget(): void
    {
        $trip = Trip::factory()->create(['counts_toward_budget' => true]);

        $this->expense(4500, null, ['trip_id' => $trip->id]);
        $this->expense(9000, null, ['trip_id' => $trip->id, 'is_settlement' => true]);

        $this->assertSame(4500, $this->spent());
    }

    public function test_a_ring_fenced_trip_is_left_out_of_the_budget(): void
    {
        $trip = Trip::factory()->ringFenced()->create();

        $this->expense(4500);
        $this->expense(60000, 20000, ['trip_id' => $trip->id]);

        $this->assertSame(4500, $this->spent());
    }

    public function test_spending_on_a_soft_deleted_trip_still_counts(): void
    {
        $trip = Trip::factory()->ringFenced()->create();

        $this->expense(4500, null, ['trip_id' => $trip->id]);

        $trip->delete();

        $this->assertSame(4500, $this->spent());
    }

    public function test_expenses_outside_the_period_are_ignored(): void
    {
        $trip = Trip::factory()->create();

        $this->expense(4500, null, ['trip_id' => $trip->id, 'date' => '2026-10-02']);

        $this->assertSame(0, $this->spent());
    }

    public function test_an_expense_on_the_last_day_of_the_period_still_counts(): void
    {
        $this->expense(4500, null, ['date' => '2026-09-30']);

        $this->assertSame(4500, $this->spent());
    }

    public function test_an_expense_on_the_first_day_of_the_period_still_counts(): void
    {
        $this->expense(4500, null, ['date' => '2026-09-01']);

        $this->assertSame(4500, $this->spent());
    }
}
