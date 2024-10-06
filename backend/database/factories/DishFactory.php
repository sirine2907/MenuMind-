<?php

namespace Database\Factories;

use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Dish>
 */
class DishFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'restaurant_id' => Restaurant::factory(),
            'category_id' => null,
            'name' => ucwords($name),
            'slug' => Str::slug($name),
            'description' => fake()->sentence(12),
            'price' => fake()->randomFloat(3, 3, 45),
            'is_vegetarian' => false,
            'is_vegan' => false,
            'spicy_level' => 0,
            'available' => true,
        ];
    }

    public function vegetarian(): static
    {
        return $this->state(fn () => ['is_vegetarian' => true]);
    }

    public function vegan(): static
    {
        return $this->state(fn () => ['is_vegetarian' => true, 'is_vegan' => true]);
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['available' => false]);
    }

    public function priced(float $price): static
    {
        return $this->state(fn () => ['price' => $price]);
    }
}
