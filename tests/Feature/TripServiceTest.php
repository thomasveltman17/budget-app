<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Services\BudgetAlertService;
use App\Services\TripService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TripServiceTest extends TestCase
{
    use RefreshDatabase;

    private TripService $service;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->mock(BudgetAlertService::class)
            ->shouldReceive('checkAllBudgets');

        $this->account = Account::factory()->create();
        $this->service = app(TripService::class);
    }

    private function trip(array $attributes = []): Trip
    {
        return Trip::factory()->create(array_merge([
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
            'participants' => 4,
        ], $attributes));
    }

    private function expense(Trip $trip, int $amount, ?int $share = null, array $attributes = []): Transaction
    {
        return Transaction::factory()->create(array_merge([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => $amount,
            'share_amount' => $share,
            'type' => 'expense',
            'date' => '2026-09-12',
        ], $attributes));
    }

    public function test_a_transaction_without_a_share_is_entirely_yours(): void
    {
        $trip = $this->trip();
        $this->expense($trip, 3000);

        $this->assertSame(3000, $this->service->outOfPocket($trip));
        $this->assertSame(3000, $this->service->myShare($trip));
        $this->assertSame(0, $this->service->outstandingBalance($trip));
    }

    public function test_fronting_a_shared_expense_leaves_the_group_owing_you(): void
    {
        $trip = $this->trip();

        // You pay €120 for a dinner for four; €30 of it is yours.
        $this->expense($trip, 12000, 3000);

        $this->assertSame(12000, $this->service->outOfPocket($trip));
        $this->assertSame(3000, $this->service->myShare($trip));
        $this->assertSame(9000, $this->service->outstandingBalance($trip));
    }

    public function test_a_settlement_transfer_clears_the_outstanding_balance(): void
    {
        $trip = $this->trip();
        $this->expense($trip, 12000, 3000);

        // The others pay you back €90 through the Tricount settlement.
        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 9000,
            'type' => 'income',
            'is_settlement' => true,
            'date' => '2026-09-15',
        ]);

        $this->assertSame(3000, $this->service->outOfPocket($trip));
        $this->assertSame(3000, $this->service->myShare($trip));
        $this->assertSame(0, $this->service->outstandingBalance($trip));
    }

    public function test_an_expense_paid_by_someone_else_counts_toward_your_share_only(): void
    {
        $trip = $this->trip();

        // A friend paid €100 for tickets; your €25 never left your account.
        $this->expense($trip, 0, 2500);

        $this->assertSame(0, $this->service->outOfPocket($trip));
        $this->assertSame(2500, $this->service->myShare($trip));
        $this->assertSame(-2500, $this->service->outstandingBalance($trip));

        // Paying your €25 settlement squares it off.
        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 2500,
            'type' => 'expense',
            'is_settlement' => true,
            'date' => '2026-09-15',
        ]);

        $this->assertSame(2500, $this->service->outOfPocket($trip));
        $this->assertSame(2500, $this->service->myShare($trip));
        $this->assertSame(0, $this->service->outstandingBalance($trip));
    }

    public function test_settlements_are_excluded_from_the_category_breakdown(): void
    {
        $trip = $this->trip();
        $food = Category::factory()->create(['name' => 'Restaurants', 'type' => 'expense']);
        $lodging = Category::factory()->create(['name' => 'Hotels', 'type' => 'expense']);

        $this->expense($trip, 12000, 3000, ['category_id' => $food->id]);
        $this->expense($trip, 8000, 2000, ['category_id' => $lodging->id]);

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'category_id' => $food->id,
            'amount' => 15000,
            'type' => 'income',
            'is_settlement' => true,
            'date' => '2026-09-15',
        ]);

        $breakdown = $this->service->breakdownByCategory($trip);

        $this->assertCount(2, $breakdown);
        $this->assertSame('Restaurants', $breakdown[0]['name']);
        $this->assertSame(3000, $breakdown[0]['amount']);
        $this->assertSame(60.0, $breakdown[0]['percentage']);
        $this->assertSame('Hotels', $breakdown[1]['name']);
        $this->assertSame(2000, $breakdown[1]['amount']);
    }

    public function test_uncategorized_transactions_are_grouped_in_the_breakdown(): void
    {
        $trip = $this->trip();
        $this->expense($trip, 4000, null, ['category_id' => null]);

        $breakdown = $this->service->breakdownByCategory($trip);

        $this->assertCount(1, $breakdown);
        $this->assertSame('Uncategorized', $breakdown[0]['name']);
        $this->assertSame(4000, $breakdown[0]['amount']);
    }

    public function test_the_daily_breakdown_covers_every_day_of_the_trip(): void
    {
        $trip = $this->trip();
        $this->expense($trip, 6000, 1500, ['date' => '2026-09-11']);
        $this->expense($trip, 4000, null, ['date' => '2026-09-13']);

        $days = $this->service->breakdownByDay($trip);

        $this->assertCount(3, $days);
        $this->assertSame(1500, $days[0]['amount']);
        $this->assertSame(0, $days[1]['amount']);
        $this->assertSame(4000, $days[2]['amount']);
    }

    public function test_budget_progress_reports_overspend(): void
    {
        $trip = $this->trip(['budget_amount' => 30000]);
        $this->expense($trip, 40000, 36000);

        $progress = $this->service->budgetProgress($trip);

        $this->assertSame(30000, $progress['budget']);
        $this->assertSame(36000, $progress['spent']);
        $this->assertSame(-6000, $progress['remaining']);
        $this->assertSame(120.0, $progress['percentage']);
        $this->assertTrue($progress['is_over']);
    }

    public function test_budget_progress_is_null_without_a_planned_budget(): void
    {
        $trip = $this->trip(['budget_amount' => null]);
        $this->expense($trip, 4000);

        $progress = $this->service->budgetProgress($trip);

        $this->assertNull($progress['percentage']);
        $this->assertNull($progress['remaining']);
        $this->assertFalse($progress['is_over']);
    }

    public function test_summary_averages_your_share_across_the_trip_days(): void
    {
        $trip = $this->trip();
        $this->expense($trip, 12000, 3000);
        $this->expense($trip, 3000);

        $summary = $this->service->summary($trip);

        $this->assertSame(15000, $summary['out_of_pocket']);
        $this->assertSame(6000, $summary['my_share']);
        $this->assertSame(9000, $summary['outstanding']);
        $this->assertSame(2000, $summary['per_day']);
    }

    public function test_auto_assign_tags_untagged_transactions_inside_the_window(): void
    {
        $trip = $this->trip();

        $inside = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'date' => '2026-09-12',
            'type' => 'expense',
            'amount' => 2000,
        ]);

        $onBoundary = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'date' => '2026-09-13',
            'type' => 'expense',
            'amount' => 2000,
        ]);

        $outside = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'date' => '2026-09-14',
            'type' => 'expense',
            'amount' => 2000,
        ]);

        $assigned = $this->service->autoAssignByDateRange($trip);

        $this->assertSame(2, $assigned);
        $this->assertSame($trip->id, $inside->fresh()->trip_id);
        $this->assertSame($trip->id, $onBoundary->fresh()->trip_id);
        $this->assertNull($outside->fresh()->trip_id);
    }

    public function test_auto_assign_never_steals_transactions_from_another_trip(): void
    {
        $trip = $this->trip();
        $otherTrip = $this->trip(['name' => 'Other trip']);

        $taken = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $otherTrip->id,
            'date' => '2026-09-12',
            'type' => 'expense',
            'amount' => 2000,
        ]);

        $this->assertSame(0, $this->service->autoAssignByDateRange($trip));
        $this->assertSame($otherTrip->id, $taken->fresh()->trip_id);
    }

    public function test_split_evenly_divides_by_the_participant_count(): void
    {
        $trip = $this->trip(['participants' => 3]);
        $one = $this->expense($trip, 9000);
        $two = $this->expense($trip, 1000);

        $updated = $this->service->splitEvenly($trip, collect([$one, $two]));

        $this->assertSame(2, $updated);
        $this->assertSame(3000, $one->fresh()->share_amount);
        $this->assertSame(333, $two->fresh()->share_amount);
    }

    public function test_soft_deleted_transactions_are_ignored(): void
    {
        $trip = $this->trip();
        $this->expense($trip, 5000)->delete();
        $this->expense($trip, 2000);

        $this->assertSame(2000, $this->service->outOfPocket($trip));
        $this->assertSame(2000, $this->service->myShare($trip));
    }

    public function test_a_single_day_trip_reports_one_day(): void
    {
        $trip = $this->trip(['start_date' => '2026-09-11', 'end_date' => '2026-09-11']);

        $this->assertSame(1, $trip->durationInDays());
        $this->assertCount(1, $this->service->breakdownByDay($trip));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }
}
