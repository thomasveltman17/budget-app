<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\BudgetAlertService;

class TransactionObserver
{
    public function __construct(
        private BudgetAlertService $alertService,
    ) {}

    public function saved(Transaction $transaction): void
    {
        $this->alertService->checkAllBudgets();
    }

    public function deleted(Transaction $transaction): void
    {
        $this->alertService->checkAllBudgets();
    }
}
