<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SavingsGoal extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'target_amount',
        'current_amount',
        'deadline',
        'notes',
        'is_completed',
    ];

    protected function casts(): array
    {
        return [
            'target_amount'  => 'integer',
            'current_amount' => 'integer',
            'deadline'       => 'date',
            'is_completed'   => 'boolean',
        ];
    }
}
