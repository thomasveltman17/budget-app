<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->numberBetween(100, 100000);
        $type = fake()->randomElement(['income', 'expense']);

        return [
            'account_id'     => Account::factory(),
            'category_id'    => null,
            'amount'         => $amount,
            'type'           => $type,
            'description'    => fake()->sentence(3),
            'date'           => fake()->dateTimeBetween('-1 year', 'now'),
            'is_reviewed'    => false,
            'ai_categorized' => false,
            'notes'          => null,
        ];
    }
}
