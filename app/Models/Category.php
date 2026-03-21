<?php

namespace App\Models;

use App\Enums\CategoryType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Category extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'budget_id',
        'color',
        'icon',
    ];

    protected function casts(): array
    {
        return [
            'type' => CategoryType::class,
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function budget(): BelongsTo
    {
        return $this->belongsTo(Budget::class);
    }

    public function isParent(): bool
    {
        return $this->parent_id === null;
    }

    public function isSubcategory(): bool
    {
        return $this->parent_id !== null;
    }

    protected static function booted(): void
    {
        static::saved(function (Category $category): void {
            if ($category->isParent() && $category->wasChanged('budget_id')) {
                $category->children()->update(['budget_id' => $category->budget_id]);
            }
        });
    }
}
