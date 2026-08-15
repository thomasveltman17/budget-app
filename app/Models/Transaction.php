<?php

namespace App\Models;

use App\Enums\TransactionType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'account_id',
        'category_id',
        'trip_id',
        'amount',
        'share_amount',
        'type',
        'description',
        'date',
        'is_reviewed',
        'is_settlement',
        'ai_categorized',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'share_amount' => 'integer',
            'type' => TransactionType::class,
            'date' => 'date',
            'is_reviewed' => 'boolean',
            'is_settlement' => 'boolean',
            'ai_categorized' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * The part of this transaction that is actually yours.
     *
     * When you front a shared expense, `amount` is what left your account and
     * `share_amount` is what you personally consumed. Without a share the whole
     * amount is yours.
     */
    public function ownShare(): int
    {
        return $this->share_amount ?? $this->amount;
    }

    /**
     * How much of this expense you fronted for other people.
     *
     * Negative when somebody else fronted it for you, which is recorded as a
     * transaction with `amount` 0 (no cash left your account) and a share.
     */
    public function frontedAmount(): int
    {
        return $this->amount - $this->ownShare();
    }

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('is_reviewed', true);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->where('is_reviewed', false);
    }

    public function scopeSettlements(Builder $query): Builder
    {
        return $query->where('is_settlement', true);
    }

    public function scopeExcludingSettlements(Builder $query): Builder
    {
        return $query->where('is_settlement', false);
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end = Carbon::create($year, $month, 1)->endOfMonth();

        return $query->whereBetween('date', [$start, $end]);
    }
}
