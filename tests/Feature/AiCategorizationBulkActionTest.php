<?php

namespace Tests\Feature;

use App\Filament\Resources\TransactionResource\Pages\ListTransactions;
use App\Jobs\AiCategorizeTransactionsJob;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class AiCategorizationBulkActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.google_ai.api_key' => 'fake-key']);
    }

    private function authenticatedUser(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_bulk_action_dispatches_job_for_uncategorized_transactions(): void
    {
        Queue::fake();
        $this->authenticatedUser();

        $transactions = Transaction::factory()->count(3)->create(['category_id' => null]);

        Livewire::test(ListTransactions::class)
            ->callTableBulkAction('aiCategorize', $transactions);

        Queue::assertPushed(AiCategorizeTransactionsJob::class, function (AiCategorizeTransactionsJob $job) use ($transactions): bool {
            return count($job->transactionIds) === 3
                && collect($job->transactionIds)->diff($transactions->pluck('id'))->isEmpty();
        });
    }

    public function test_bulk_action_skips_already_categorized_transactions(): void
    {
        Queue::fake();
        $this->authenticatedUser();

        $category = Category::factory()->create();
        $categorized = Transaction::factory()->create(['category_id' => $category->id]);
        $uncategorized = Transaction::factory()->create(['category_id' => null]);

        Livewire::test(ListTransactions::class)
            ->callTableBulkAction('aiCategorize', collect([$categorized, $uncategorized]));

        Queue::assertPushed(AiCategorizeTransactionsJob::class, function (AiCategorizeTransactionsJob $job) use ($uncategorized): bool {
            return $job->transactionIds === [$uncategorized->id];
        });
    }

    public function test_bulk_action_does_not_dispatch_job_when_all_transactions_are_categorized(): void
    {
        Queue::fake();
        $this->authenticatedUser();

        $category = Category::factory()->create();
        $transactions = Transaction::factory()->count(2)->create(['category_id' => $category->id]);

        Livewire::test(ListTransactions::class)
            ->callTableBulkAction('aiCategorize', $transactions);

        Queue::assertNothingPushed();
    }
}
