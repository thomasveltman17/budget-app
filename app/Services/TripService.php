<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Transaction;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Money maths for trips.
 *
 * A trip has two different totals and both are useful:
 *
 *  - "out of pocket" — net cash that actually left your account, settlement
 *    transfers included. Exact once the group has settled up, too high before.
 *  - "my share" — what you personally consumed, settlements excluded. Correct
 *    from day one and the only total that gives a truthful category breakdown.
 *
 * The gap between them is what the group still owes you (or you still owe the
 * group), which should land on zero once the Tricount is settled.
 */
class TripService
{
    /**
     * Net cash that left your account for this trip, settlements included.
     */
    public function outOfPocket(Trip $trip): int
    {
        return (int) $this->baseQuery($trip)
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');
    }

    /**
     * What the trip actually cost you, regardless of who fronted what.
     */
    public function myShare(Trip $trip): int
    {
        return (int) $this->baseQuery($trip)
            ->excludingSettlements()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN COALESCE(share_amount, amount) ELSE -COALESCE(share_amount, amount) END), 0) AS total")
            ->value('total');
    }

    /**
     * Positive when the group still owes you, negative when you still owe them.
     *
     * Converges to zero as settlement transfers land.
     */
    public function outstandingBalance(Trip $trip): int
    {
        return $this->outOfPocket($trip) - $this->myShare($trip);
    }

    /**
     * Total the group spent on expenses you fronted — not the whole trip's group
     * total, since expenses paid by others never reach this app in full.
     */
    public function settledTotal(Trip $trip): int
    {
        return (int) $this->baseQuery($trip)
            ->settlements()
            ->selectRaw("COALESCE(SUM(CASE WHEN type = 'expense' THEN amount ELSE -amount END), 0) AS total")
            ->value('total');
    }

    /**
     * Your share broken down by category, largest first.
     *
     * @return Collection<int, array{category: ?Category, name: string, color: string, amount: int, percentage: float}>
     */
    public function breakdownByCategory(Trip $trip): Collection
    {
        $share = $this->myShare($trip);

        $rows = $this->baseQuery($trip)
            ->excludingSettlements()
            ->where('type', 'expense')
            ->with('category.parent')
            ->get()
            ->groupBy(fn (Transaction $transaction): string => (string) ($transaction->category_id ?? 'none'))
            ->map(function (Collection $transactions): array {
                $category = $transactions->first()->category;

                return [
                    'category' => $category,
                    'name' => $category?->parent
                        ? $category->parent->name.' › '.$category->name
                        : ($category?->name ?? 'Uncategorized'),
                    'color' => $category?->color ?? '#9CA3AF',
                    'amount' => $transactions->sum(fn (Transaction $transaction): int => $transaction->ownShare()),
                ];
            })
            ->values();

        return $rows
            ->map(function (array $row) use ($share): array {
                $row['percentage'] = $share > 0
                    ? round($row['amount'] / $share * 100, 1)
                    : 0.0;

                return $row;
            })
            ->sortByDesc('amount')
            ->values();
    }

    /**
     * Your share per calendar day, covering every day of the trip so quiet days
     * still show up as zero.
     *
     * @return Collection<int, array{date: Carbon, label: string, amount: int}>
     */
    public function breakdownByDay(Trip $trip): Collection
    {
        $perDay = $this->baseQuery($trip)
            ->excludingSettlements()
            ->where('type', 'expense')
            ->get()
            ->groupBy(fn (Transaction $transaction): string => $transaction->date->toDateString())
            ->map(fn (Collection $transactions): int => $transactions->sum(
                fn (Transaction $transaction): int => $transaction->ownShare()
            ));

        $days = collect();

        for ($date = $trip->start_date->copy(); $date->lte($trip->end_date); $date->addDay()) {
            $days->push([
                'date' => $date->copy(),
                'label' => $date->format('D d M'),
                'amount' => $perDay->get($date->toDateString(), 0),
            ]);
        }

        return $days;
    }

    /**
     * Progress against the trip's planned budget.
     *
     * @return array{budget: ?int, spent: int, remaining: ?int, percentage: ?float, is_over: bool}
     */
    public function budgetProgress(Trip $trip): array
    {
        $spent = $this->myShare($trip);

        if ($trip->budget_amount === null || $trip->budget_amount <= 0) {
            return [
                'budget' => $trip->budget_amount,
                'spent' => $spent,
                'remaining' => null,
                'percentage' => null,
                'is_over' => false,
            ];
        }

        return [
            'budget' => $trip->budget_amount,
            'spent' => $spent,
            'remaining' => $trip->budget_amount - $spent,
            'percentage' => round($spent / $trip->budget_amount * 100, 1),
            'is_over' => $spent > $trip->budget_amount,
        ];
    }

    /**
     * Everything the trip overview needs, in one pass.
     *
     * @return array{out_of_pocket: int, my_share: int, outstanding: int, per_day: int, budget: array{budget: ?int, spent: int, remaining: ?int, percentage: ?float, is_over: bool}}
     */
    public function summary(Trip $trip): array
    {
        $outOfPocket = $this->outOfPocket($trip);
        $myShare = $this->myShare($trip);
        $days = $trip->durationInDays();

        return [
            'out_of_pocket' => $outOfPocket,
            'my_share' => $myShare,
            'outstanding' => $outOfPocket - $myShare,
            'per_day' => $days > 0 ? (int) round($myShare / $days) : 0,
            'budget' => $this->budgetProgress($trip),
        ];
    }

    /**
     * Tag every untagged transaction inside the trip's date window.
     *
     * Only touches transactions that belong to no trip yet, so re-running it
     * never steals rows from another trip or undoes a manual correction.
     *
     * Returns the number of transactions assigned.
     */
    public function autoAssignByDateRange(Trip $trip, ?Carbon $from = null, ?Carbon $until = null): int
    {
        return Transaction::query()
            ->whereNull('trip_id')
            ->whereDate('date', '>=', ($from ?? $trip->start_date)->toDateString())
            ->whereDate('date', '<=', ($until ?? $trip->end_date)->toDateString())
            ->update(['trip_id' => $trip->id]);
    }

    /**
     * Split an amount evenly across the trip's participants and store it as the
     * share on every given transaction.
     *
     * @param  Collection<int, Transaction>  $transactions
     */
    public function splitEvenly(Trip $trip, Collection $transactions): int
    {
        $transactions->each(function (Transaction $transaction) use ($trip): void {
            $transaction->update(['share_amount' => $trip->evenShareOf($transaction->amount)]);
        });

        return $transactions->count();
    }

    protected function baseQuery(Trip $trip): Builder
    {
        return Transaction::query()->where('trip_id', $trip->id);
    }
}
