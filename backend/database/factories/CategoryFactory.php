<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'restaurant_id' => Restaurant::factory(),
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'position' => fake()->numberBetween(0, 10),
        ];
    }
}
