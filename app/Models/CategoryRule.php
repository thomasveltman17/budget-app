<?php

namespace App\Models;

use App\Enums\MatchType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CategoryRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'keyword',
        'match_type',
        'category_id',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'match_type' => MatchType::class,
            'priority'   => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('id');
    }

    /**
     * Check whether this rule matches the given transaction description.
     */
    public function matches(string $description): bool
    {
        $needle    = strtolower($this->keyword);
        $haystack  = strtolower($description);

        return match ($this->match_type) {
            MatchType::Contains   => str_contains($haystack, $needle),
            MatchType::StartsWith => str_starts_with($haystack, $needle),
            MatchType::EndsWith   => str_ends_with($haystack, $needle),
        };
    }
}
