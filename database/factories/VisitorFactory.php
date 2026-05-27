<?php

namespace Database\Factories;

use App\Models\Visitor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Visitor>
 */
class VisitorFactory extends Factory
{
    protected $model = Visitor::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'document_number' => (string) fake()->unique()->numerify('########'),
            'document_type' => 'DUI',
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->numerify('########'),
            'company' => fake()->company(),
        ];
    }
}
