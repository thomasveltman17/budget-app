<?php

namespace App\Models;

use App\Enums\TripStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Trip extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'destination',
        'start_date',
        'end_date',
        'budget_amount',
        'participants',
        'status',
        'counts_toward_budget',
        'savings_goal_id',
        'color',
        'icon',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'budget_amount' => 'integer',
            'participants' => 'integer',
            'status' => TripStatus::class,
            'counts_toward_budget' => 'boolean',
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function savingsGoal(): BelongsTo
    {
        return $this->belongsTo(SavingsGoal::class);
    }

    // ── Scopes ────────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TripStatus::Active);
    }

    public function scopeUnsettled(Builder $query): Builder
    {
        return $query->whereIn('status', [TripStatus::Active, TripStatus::Settling]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function durationInDays(): int
    {
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * Your share when an expense is split evenly across everyone on the trip.
     */
    public function evenShareOf(int $amount): int
    {
        return (int) round($amount / max(1, $this->participants));
    }
}
