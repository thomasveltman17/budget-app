<?php

namespace App\Services;

use App\Models\CategoryRule;
use App\Models\Transaction;

class CategoryRuleService
{
    /**
     * Apply the first matching rule to a transaction.
     * Skips transactions that already have a category (preserves manual assignments).
     *
     * @return bool  true if a rule matched and category was set
     */
    public function applyToTransaction(Transaction $transaction): bool
    {
        if ($transaction->category_id !== null) {
            return false;
        }

        $rules = CategoryRule::ordered()->with('category')->get();

        foreach ($rules as $rule) {
            if ($rule->matches($transaction->description)) {
                $transaction->updateQuietly(['category_id' => $rule->category_id]);

                return true;
            }
        }

        return false;
    }

    /**
     * Apply rules to all uncategorized transactions.
     *
     * @return int  number of transactions that were newly categorized
     */
    public function applyToAll(): int
    {
        $rules = CategoryRule::ordered()->with('category')->get();

        if ($rules->isEmpty()) {
            return 0;
        }

        $count = 0;

        Transaction::whereNull('category_id')
            ->whereNull('deleted_at')
            ->each(function (Transaction $transaction) use ($rules, &$count): void {
                foreach ($rules as $rule) {
                    if ($rule->matches($transaction->description)) {
                        $transaction->updateQuietly(['category_id' => $rule->category_id]);
                        $count++;
                        break;
                    }
                }
            });

        return $count;
    }
}
