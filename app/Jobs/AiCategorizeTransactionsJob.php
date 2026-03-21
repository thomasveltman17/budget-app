<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Models\User;
use App\Services\AiCategorizationService;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AiCategorizeTransactionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int>  $transactionIds
     */
    public function __construct(public readonly array $transactionIds) {}

    public function handle(AiCategorizationService $service): void
    {
        $transactions = Transaction::whereIn('id', $this->transactionIds)
            ->whereNull('category_id')
            ->whereNull('deleted_at')
            ->get()
            ->all();

        $count = $service->categorize($transactions);

        $user = User::first();

        if ($user === null) {
            return;
        }

        Notification::make()
            ->title("{$count} transaction(s) categorized by AI")
            ->success()
            ->sendToDatabase($user);
    }
}
