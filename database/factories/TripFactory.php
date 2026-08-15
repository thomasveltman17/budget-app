<?php

namespace Database\Factories;

use App\Enums\TripStatus;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = Carbon::instance(fake()->dateTimeBetween('-6 months', 'now'))->startOfDay();

        return [
            'name' => fake()->city().' weekend',
            'destination' => fake()->city(),
            'start_date' => $start,
            'end_date' => $start->copy()->addDays(2),
            'budget_amount' => fake()->numberBetween(20000, 100000),
            'participants' => fake()->numberBetween(2, 6),
            'status' => TripStatus::Settled,
            'counts_toward_budget' => true,
            'savings_goal_id' => null,
            'color' => '#F59E0B',
            'icon' => 'heroicon-o-map',
            'notes' => null,
        ];
    }

    public function planned(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TripStatus::Planned,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => TripStatus::Active,
            'start_date' => Carbon::today()->subDay(),
            'end_date' => Carbon::today()->addDay(),
        ]);
    }

    public function ringFenced(): static
    {
        return $this->state(fn (array $attributes): array => [
            'counts_toward_budget' => false,
        ]);
    }
}
