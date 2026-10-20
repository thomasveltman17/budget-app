<?php

namespace Tests\Feature;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Filament\Resources\Trips\Pages\CreateTrip;
use App\Filament\Resources\Trips\Pages\ListTrips;
use App\Filament\Resources\Trips\Pages\ViewTrip;
use App\Filament\Resources\Trips\RelationManagers\TransactionsRelationManager;
use App\Filament\Resources\Trips\Widgets\TripOverviewWidget;
use App\Filament\Widgets\ActiveTripWidget;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Services\BudgetAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TripResourceTest extends TestCase
{
    use RefreshDatabase;

    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        $this->mock(BudgetAlertService::class)
            ->shouldReceive('checkAllBudgets');

        $this->account = Account::factory()->create();
    }

    public function test_the_trip_list_renders(): void
    {
        $trip = Trip::factory()->create(['name' => 'Lisbon weekend']);

        Livewire::test(ListTrips::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$trip]);
    }

    public function test_a_trip_can_be_created(): void
    {
        Livewire::test(CreateTrip::class)
            ->fillForm([
                'name' => 'Lisbon weekend',
                'start_date' => '2026-09-11',
                'end_date' => '2026-09-13',
                'participants' => 4,
                'status' => 'planned',
                'budget_amount' => 250,
                'counts_toward_budget' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas(Trip::class, [
            'name' => 'Lisbon weekend',
            'participants' => 4,
            'budget_amount' => 25000,
        ]);
    }

    public function test_a_trip_cannot_end_before_it_starts(): void
    {
        Livewire::test(CreateTrip::class)
            ->fillForm([
                'name' => 'Backwards trip',
                'start_date' => '2026-09-13',
                'end_date' => '2026-09-11',
                'participants' => 2,
                'status' => 'planned',
            ])
            ->call('create')
            ->assertHasFormErrors(['end_date']);
    }

    public function test_the_trip_overview_page_renders(): void
    {
        $trip = Trip::factory()->create([
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
        ]);

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 12000,
            'share_amount' => 3000,
            'type' => 'expense',
            'date' => '2026-09-12',
        ]);

        Livewire::test(ViewTrip::class, ['record' => $trip->getKey()])
            ->assertOk();

        Livewire::test(TripOverviewWidget::class, ['record' => $trip])
            ->assertOk()
            ->assertSee('What this trip cost you')
            ->assertSee('The group still owes you')
            ->assertSee('€90.00');
    }

    public function test_pulling_in_transactions_tags_them_to_the_trip(): void
    {
        $trip = Trip::factory()->create([
            'start_date' => '2026-09-11',
            'end_date' => '2026-09-13',
        ]);

        $inside = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'date' => '2026-09-12',
            'type' => 'expense',
            'amount' => 2000,
        ]);

        Livewire::test(ListTrips::class)
            ->callTableAction('assignTransactions', $trip);

        $this->assertSame($trip->id, $inside->fresh()->trip_id);
    }

    public function test_the_bulk_action_assigns_transactions_to_a_trip(): void
    {
        $trip = Trip::factory()->create(['participants' => 4]);

        $transactions = Transaction::factory()->count(2)->create([
            'account_id' => $this->account->id,
            'amount' => 12000,
            'type' => 'expense',
        ]);

        Livewire::test(ListTransactions::class)
            ->callTableBulkAction('assignTrip', $transactions, [
                'trip_id' => $trip->id,
                'split_evenly' => false,
            ]);

        $transactions->each(function (Transaction $transaction) use ($trip): void {
            $this->assertSame($trip->id, $transaction->fresh()->trip_id);
            $this->assertNull($transaction->fresh()->share_amount);
        });
    }

    public function test_the_bulk_action_can_split_evenly_while_assigning(): void
    {
        $trip = Trip::factory()->create(['participants' => 4]);

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'amount' => 12000,
            'type' => 'expense',
        ]);

        Livewire::test(ListTransactions::class)
            ->callTableBulkAction('assignTrip', [$transaction], [
                'trip_id' => $trip->id,
                'split_evenly' => true,
            ]);

        $this->assertSame(3000, $transaction->fresh()->share_amount);
    }

    public function test_the_relation_manager_can_split_a_transaction_evenly(): void
    {
        $trip = Trip::factory()->create(['participants' => 4]);

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 12000,
            'type' => 'expense',
        ]);

        Livewire::test(TransactionsRelationManager::class, [
            'ownerRecord' => $trip,
            'pageClass' => ViewTrip::class,
        ])
            ->assertOk()
            ->callTableAction('splitEvenly', $transaction);

        $this->assertSame(3000, $transaction->fresh()->share_amount);
    }

    public function test_the_relation_manager_can_mark_a_transaction_as_settlement(): void
    {
        $trip = Trip::factory()->create();

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 9000,
            'share_amount' => 2000,
            'type' => 'income',
        ]);

        Livewire::test(TransactionsRelationManager::class, [
            'ownerRecord' => $trip,
            'pageClass' => ViewTrip::class,
        ])
            ->callTableBulkAction('markSettlement', [$transaction]);

        $transaction->refresh();

        $this->assertTrue($transaction->is_settlement);
        $this->assertNull($transaction->share_amount);
    }

    public function test_the_relation_manager_can_remove_a_transaction_from_the_trip(): void
    {
        $trip = Trip::factory()->create();

        $transaction = Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 4000,
            'type' => 'expense',
        ]);

        Livewire::test(TransactionsRelationManager::class, [
            'ownerRecord' => $trip,
            'pageClass' => ViewTrip::class,
        ])
            ->callTableBulkAction('removeFromTrip', [$transaction]);

        $this->assertNull($transaction->fresh()->trip_id);
        $this->assertNotNull($transaction->fresh());
    }

    public function test_the_dashboard_widget_is_hidden_without_an_unsettled_trip(): void
    {
        Trip::factory()->create(['status' => 'settled']);

        $this->assertFalse(ActiveTripWidget::canView());
    }

    public function test_the_dashboard_widget_shows_while_a_trip_is_unsettled(): void
    {
        $trip = Trip::factory()->active()->create();

        Transaction::factory()->create([
            'account_id' => $this->account->id,
            'trip_id' => $trip->id,
            'amount' => 12000,
            'share_amount' => 3000,
            'type' => 'expense',
            'date' => today(),
        ]);

        $this->assertTrue(ActiveTripWidget::canView());

        Livewire::test(ActiveTripWidget::class)
            ->assertOk()
            ->assertSee($trip->name)
            ->assertSee('The group owes you');
    }
}
