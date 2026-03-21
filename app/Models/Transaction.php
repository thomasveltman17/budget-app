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
        'amount',
        'type',
        'description',
        'date',
        'is_reviewed',
        'ai_categorized',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'amount'      => 'integer',
            'type'        => TransactionType::class,
            'date'        => 'date',
            'is_reviewed'     => 'boolean',
            'ai_categorized'  => 'boolean',
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

    public function scopeReviewed(Builder $query): Builder
    {
        return $query->where('is_reviewed', true);
    }

    public function scopeUnreviewed(Builder $query): Builder
    {
        return $query->where('is_reviewed', false);
    }

    public function scopeForPeriod(Builder $query, int $year, int $month): Builder
    {
        $start = Carbon::create($year, $month, 1)->startOfMonth();
        $end   = Carbon::create($year, $month, 1)->endOfMonth();

        return $query->whereBetween('date', [$start, $end]);
    }
}
