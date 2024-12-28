<?php

namespace Tests\Feature;

use App\Models\Allergen;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Services\Documents\DocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private Restaurant $restaurant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->restaurant = Restaurant::factory()->create();
        $peanuts = Allergen::factory()->create(['slug' => 'peanuts']);

        Dish::factory()->for($this->restaurant)->vegetarian()->priced(11.5)->create([
            'name' => 'Ojja Legumes', 'description' => 'Eggs poached in a spicy tomato sauce.',
        ]);
        Dish::factory()->for($this->restaurant)->priced(24)->create([
            'name' => 'Couscous Lamb', 'description' => 'Hand-rolled couscous with lamb shoulder.',
        ]);
        Dish::factory()->for($this->restaurant)->vegetarian()->priced(5.5)->create([
            'name' => 'Mhalbia', 'description' => 'Rice pudding topped with crushed peanuts.',
        ])->allergens()->attach($peanuts);
    }

    public function test_dish_search_falls_back_to_sql_when_typesense_is_disabled(): void
    {
        $this->getJson('/api/search/dishes?q=lamb couscous')
            ->assertOk()
            ->assertJsonPath('data.engine', 'sql_keyword')
            ->assertJsonPath('data.hits.0.name', 'Couscous Lamb');
    }

    public function test_constraint_only_queries_rely_on_filters(): void
    {
        $this->getJson('/api/search/dishes?q=vegetarian dishes, allergic to peanuts&vegetarian=1&exclude_allergens=peanuts&max_price=25')
            ->assertOk()
            ->assertJsonCount(1, 'data.hits')
            ->assertJsonPath('data.hits.0.name', 'Ojja Legumes')
            ->assertJsonPath('data.filters.exclude_allergens', ['peanuts']);
    }

    public function test_dish_search_uses_typesense_hybrid_search_when_available(): void
    {
        config(['typesense.enabled' => true]);

        Http::fake([
            '*/collections/dishes/documents/search*' => Http::response(['hits' => [[
                'document' => [
                    'id' => '7', 'name' => 'Ojja Legumes', 'description' => 'Spicy eggs', 'category' => 'Mains',
                    'price' => 11.5, 'is_vegetarian' => true, 'is_vegan' => false, 'spicy_level' => 2,
                    'available' => true, 'allergens' => ['eggs'],
                ],
                'hybrid_search_info' => ['rank_fusion_score' => 0.82],
            ]]]),
        ]);

        $this->getJson('/api/search/dishes?q=something warm and spicy&vegetarian=1&max_price=25&exclude_allergens=peanuts')
            ->assertOk()
            ->assertJsonPath('data.engine', 'typesense_hybrid')
            ->assertJsonPath('data.hits.0.id', 7)
            ->assertJsonPath('data.hits.0.score', 0.82);

        Http::assertSent(function (Request $request) {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->hasHeader('X-TYPESENSE-API-KEY')
                && $query['query_by'] === 'name,description,category,embedding'
                && str_starts_with($query['vector_query'], 'embedding:([], alpha:')
                && str_contains($query['filter_by'], 'is_vegetarian:=true')
                && str_contains($query['filter_by'], 'price:<=25')
                && str_contains($query['filter_by'], 'allergens:!=[`peanuts`]');
        });
    }

    public function test_dish_search_degrades_gracefully_when_typesense_errors(): void
    {
        config(['typesense.enabled' => true]);
        Http::fake(['*' => Http::response('boom', 500)]);

        $this->getJson('/api/search/dishes?q=lamb')
            ->assertOk()
            ->assertJsonPath('data.engine', 'sql_keyword')
            ->assertJsonPath('data.hits.0.name', 'Couscous Lamb');
    }

    public function test_document_search_returns_cited_chunks(): void
    {
        app(DocumentService::class)->ingestText(
            $this->restaurant,
            'Delivery FAQ',
            "# Delivery FAQ\n\n## Fees\n\nDelivery costs 3 TND, free above 60 TND.\n\n## Hours\n\nWe open at noon every day.",
            'faq.md',
        );

        $this->getJson('/api/search/docs?q=how much is delivery')
            ->assertOk()
            ->assertJsonPath('data.engine', 'sql_keyword')
            ->assertJsonPath('data.hits.0.document_title', 'Delivery FAQ')
            ->assertJsonPath('data.hits.0.heading', 'Fees');
    }

    public function test_document_search_requires_a_query(): void
    {
        $this->getJson('/api/search/docs')->assertUnprocessable()->assertJsonValidationErrorFor('q', 'error.details');
    }
}
