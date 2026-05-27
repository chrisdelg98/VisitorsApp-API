<?php

namespace Database\Factories;

use App\Models\Station;
use App\Models\Visit;
use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'station_id' => Station::factory(),
            'visitor_id' => Visitor::factory(),
            'visitor_type' => 'guest',
            'visit_reason' => 'Meeting',
            'visiting_person' => fake()->name(),
            'check_in' => now(),
            'check_out' => null,
            'status' => 'active',
            'badge_printed' => false,
            'reentry_count' => 0,
        ];
    }

    /**
     * A completed (checked-out) visit.
     */
    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => 'completed',
            'check_out' => now(),
            'checkout_type' => 'visitor',
        ]);
    }
}
