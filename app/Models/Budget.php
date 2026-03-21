<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
            'is_active'  => 'boolean',
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
     */
    public function spentInPeriod(Carbon $periodStart, Carbon $periodEnd): int
    {
        return (int) Transaction::query()
            ->join('categories', 'transactions.category_id', '=', 'categories.id')
            ->where('categories.budget_id', $this->id)
            ->where('transactions.type', 'expense')
            ->whereBetween('transactions.date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->whereNull('transactions.deleted_at')
            ->whereNull('categories.deleted_at')
            ->sum('transactions.amount');
    }
}
