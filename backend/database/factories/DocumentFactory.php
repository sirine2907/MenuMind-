<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Restaurant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'restaurant_id' => Restaurant::factory(),
            'title' => fake()->sentence(3),
            'original_filename' => 'policy.md',
            'mime_type' => 'text/markdown',
            'content' => fake()->paragraphs(3, true),
        ];
    }
}
