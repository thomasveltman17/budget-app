<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\JoinClause;

class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'percentage',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'percentage' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->withoutTrashed();
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Derived budget ceiling in cents: floor(monthly_income × percentage / 100).
     */
    public function allocatedAmount(User $user): int
    {
        return (int) floor($user->monthly_income * $this->percentage / 100);
    }

    /**
     * Sum of all expense transactions in the given period for this budget's categories.
     *
     * Shared expenses count for your share only, so fronting a €120 dinner for
     * four costs this budget €30. Settlement transfers move money without
     * consuming anything, and trips flagged as not counting toward budgets are
     * left out entirely.
     */
    public function spentInPeriod(Carbon $periodStart, Carbon $periodEnd): int
    {
        return (int) Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->leftJoin('trips', function (JoinClause $join): void {
                $join->on('transactions.trip_id', '=', 'trips.id')
                    ->whereNull('trips.deleted_at');
            })
            ->where('categories.budget_id', $this->id)
            ->where('transactions.type', 'expense')
            ->where('transactions.is_settlement', false)
            ->whereDate('transactions.date', '>=', $periodStart->toDateString())
            ->whereDate('transactions.date', '<=', $periodEnd->toDateString())
            ->whereNull('transactions.deleted_at')
            ->whereNull('categories.deleted_at')
            ->where(function (Builder $query): void {
                $query->whereNull('trips.id')
                    ->orWhere('trips.counts_toward_budget', true);
            })
            ->selectRaw('COALESCE(SUM(COALESCE(transactions.share_amount, transactions.amount)), 0) AS total')
            ->value('total');
    }
}
