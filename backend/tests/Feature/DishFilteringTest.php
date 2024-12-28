<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Category;
use App\Models\Dish;
use App\Models\Restaurant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DishFilteringTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $mains = Category::factory()->for($this->restaurant)->create(['slug' => 'mains', 'name' => 'Mains']);
        $desserts = Category::factory()->for($this->restaurant)->create(['slug' => 'desserts', 'name' => 'Desserts']);

        $peanuts = Allergen::factory()->create(['slug' => 'peanuts', 'name' => 'Peanuts']);
        $gluten = Allergen::factory()->create(['slug' => 'gluten', 'name' => 'Gluten']);

        $this->dish('Couscous Lamb', 24, $mains, allergens: [$gluten]);
        $this->dish('Vegetable Couscous', 17.5, $mains, vegetarian: true, vegan: true, allergens: [$gluten]);
        $this->dish('Ojja Legumes', 11.5, $mains, vegetarian: true, spicy: 2);
        $this->dish('Couscous Mesfouf', 9, $desserts, vegetarian: true, allergens: [$gluten, $peanuts]);
        $this->dish('Mhalbia', 5.5, $desserts, vegetarian: true, allergens: [$peanuts]);
        $this->dish('Seasonal Juice', 5, $desserts, vegetarian: true, available: false);
    }

    public function test_it_lists_available_dishes_with_pagination(): void
    {
        $this->getJson('/api/dishes?per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonStructure(['data' => [['id', 'name', 'price', 'currency', 'allergens', 'category']], 'links', 'meta']);
    }

    public function test_it_filters_vegetarian_dishes(): void
    {
        $names = $this->names('/api/dishes?vegetarian=1');

        $this->assertEqualsCanonicalizing(['Vegetable Couscous', 'Ojja Legumes', 'Couscous Mesfouf', 'Mhalbia'], $names);
    }

    public function test_it_filters_by_price_range(): void
    {
        $this->assertEqualsCanonicalizing(
            ['Ojja Legumes', 'Couscous Mesfouf'],
            $this->names('/api/dishes?min_price=6&max_price=12'),
        );
    }

    public function test_it_excludes_dishes_containing_given_allergens(): void
    {
        $names = $this->names('/api/dishes?exclude_allergens=peanuts');

        $this->assertNotContains('Couscous Mesfouf', $names);
        $this->assertNotContains('Mhalbia', $names);
        $this->assertCount(3, $names);
    }

    public function test_allergen_filter_understands_synonyms(): void
    {
        // "peanut" (singular) resolves to the canonical "peanuts" slug.
        $this->assertNotContains('Mhalbia', $this->names('/api/dishes?exclude_allergens=peanut'));
    }

    public function test_filters_combine_like_the_assistant_query(): void
    {
        // "I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?"
        $names = $this->names('/api/dishes?vegetarian=1&max_price=25&exclude_allergens[]=peanuts&sort=price_asc');

        $this->assertSame(['Ojja Legumes', 'Vegetable Couscous'], $names);
    }

    public function test_it_filters_by_category_and_spiciness(): void
    {
        $this->assertEqualsCanonicalizing(['Couscous Mesfouf', 'Mhalbia'], $this->names('/api/dishes?category=desserts'));
        $this->assertNotContains('Ojja Legumes', $this->names('/api/dishes?max_spicy=1'));
    }

    public function test_it_can_include_unavailable_dishes(): void
    {
        $this->assertContains('Seasonal Juice', $this->names('/api/dishes?available=0'));
    }

    public function test_it_rejects_invalid_filters(): void
    {
        $this->getJson('/api/dishes?min_price=20&max_price=10&sort=random&max_spicy=9')
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'validation_failed')
            ->assertJsonValidationErrorFor('max_price', 'error.details')
            ->assertJsonValidationErrorFor('sort', 'error.details')
            ->assertJsonValidationErrorFor('max_spicy', 'error.details');
    }

    public function test_it_shows_a_single_dish_and_404s_cleanly(): void
    {
        $dish = Dish::where('name', 'Mhalbia')->first();

        $this->getJson("/api/dishes/{$dish->id}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Mhalbia')
            ->assertJsonPath('data.allergens.0.slug', 'peanuts');

        $this->getJson('/api/dishes/99999')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found')
            ->assertJsonPath('error.message', 'Dish not found.');
    }

    public function test_allergen_check_flags_conflicts(): void
    {
        $safe = Dish::where('name', 'Ojja Legumes')->value('id');
        $unsafe = Dish::where('name', 'Mhalbia')->value('id');

        $this->postJson('/api/dishes/allergen-check', ['dish_ids' => [$safe, $unsafe, 999], 'allergens' => ['peanut']])
            ->assertOk()
            ->assertJsonPath('data.allergens_checked', ['peanuts'])
            ->assertJsonPath('data.results.0.safe', true)
            ->assertJsonPath('data.results.1.safe', false)
            ->assertJsonPath('data.results.1.conflicts', ['peanuts'])
            ->assertJsonPath('data.results.2.found', false);
    }

    /**
     * @return list<string>
     */
    private function names(string $uri): array
    {
        return $this->getJson($uri)->assertOk()->json('data.*.name');
    }

    /**
     * @param  list<Allergen>  $allergens
     */
    private function dish(
        string $name,
        float $price,
        Category $category,
        bool $vegetarian = false,
        bool $vegan = false,
        int $spicy = 0,
        bool $available = true,
        array $allergens = [],
    ): Dish {
        $dish = Dish::factory()->for($this->restaurant)->create([
            'name' => $name,
            'category_id' => $category->id,
            'price' => $price,
            'is_vegetarian' => $vegetarian,
            'is_vegan' => $vegan,
            'spicy_level' => $spicy,
            'available' => $available,
        ]);
        $dish->allergens()->sync(collect($allergens)->pluck('id'));

        return $dish;
    }
}
