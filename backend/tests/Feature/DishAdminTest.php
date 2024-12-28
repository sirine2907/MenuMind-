<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DishAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Restaurant::factory()->create();
        Allergen::factory()->create(['slug' => 'gluten']);
        Allergen::factory()->create(['slug' => 'eggs']);
    }

    public function test_customers_cannot_manage_dishes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/dishes', $this->payload())
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_guests_cannot_manage_dishes(): void
    {
        $this->postJson('/api/dishes', $this->payload())->assertUnauthorized();
    }

    public function test_an_admin_can_create_a_dish_with_allergens(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/dishes', $this->payload(['allergens' => ['gluten', 'eggs']]))
            ->assertCreated()
            ->assertJsonPath('data.name', 'Brik à l’Œuf')
            ->assertJsonPath('data.slug', 'brik-a-loeuf')
            ->assertJsonPath('data.price', 6.5)
            ->assertJsonCount(2, 'data.allergens');
    }

    public function test_vegan_dishes_are_always_vegetarian(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/dishes', $this->payload(['is_vegan' => true, 'is_vegetarian' => false]))
            ->assertCreated()
            ->assertJsonPath('data.is_vegetarian', true);
    }

    public function test_dish_creation_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson('/api/dishes', ['price' => -3, 'spicy_level' => 7, 'allergens' => ['unicorn']])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name', 'error.details')
            ->assertJsonValidationErrorFor('description', 'error.details')
            ->assertJsonValidationErrorFor('price', 'error.details')
            ->assertJsonValidationErrorFor('spicy_level', 'error.details')
            ->assertJsonValidationErrorFor('allergens.0', 'error.details');
    }

    public function test_an_admin_can_partially_update_and_delete_a_dish(): void
    {
        Sanctum::actingAs(User::factory()->admin()->create());
        $dish = Dish::factory()->for(Restaurant::first())->create(['price' => 10]);

        $this->patchJson("/api/dishes/{$dish->id}", ['price' => 12.25, 'available' => false, 'allergens' => ['eggs']])
            ->assertOk()
            ->assertJsonPath('data.price', 12.25)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.allergens.0.slug', 'eggs');

        $this->deleteJson("/api/dishes/{$dish->id}")->assertNoContent();
        $this->assertModelMissing($dish);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Brik à l’Œuf',
            'description' => 'Crispy pastry with egg and tuna.',
            'price' => 6.5,
            'spicy_level' => 0,
        ], $overrides);
    }
}
