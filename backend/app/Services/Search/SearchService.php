<?php

namespace App\Services\Search;

use App\Models\Dish;
use App\Models\DocumentChunk;
use App\Models\Restaurant;
use App\Repositories\DishRepository;
use App\Support\AllergenResolver;
use App\Support\DishFilters;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Hybrid search (keyword + vector) with Typesense.
// If Typesense is down we fall back to a simple keyword search in the database.
class SearchService
{
    // words that don't help to find a dish (diet and allergy words are handled by the filters)
    private const IGNORED_WORDS = [
        'what', 'which', 'with', 'without', 'dish', 'dishes', 'food', 'want', 'would', 'like', 'please',
        'under', 'less', 'than', 'more', 'menu', 'order', 'give', 'show', 'there', 'that', 'this', 'from',
        'about', 'does', 'have', 'some', 'something', 'much', 'dinar', 'dinars', 'cheap', 'your',
        'allergic', 'allergy', 'allergies', 'vegetarian', 'vegan', 'veggie',
    ];

    public function __construct(
        private TypesenseClient $client,
        private DishRepository $dishes,
    ) {}

    public function searchDishes(Restaurant $restaurant, string $query, DishFilters $filters, int $limit = 8): array
    {
        $engine = 'typesense_hybrid';
        $hits = null;

        if (config('typesense.enabled')) {
            try {
                $hits = $this->typesenseDishes($restaurant, $query, $filters, $limit);
            } catch (\Throwable $e) {
                Log::warning('Typesense search failed, using the database instead', ['error' => $e->getMessage()]);
            }
        }

        if ($hits === null) {
            $engine = 'sql_keyword';
            $hits = $this->databaseDishes($restaurant, $query, $filters, $limit);
        }

        return ['engine' => $engine, 'query' => $query, 'filters' => $filters->toArray(), 'hits' => $hits];
    }

    public function searchDocs(Restaurant $restaurant, string $query, int $limit = 4): array
    {
        $engine = 'typesense_hybrid';
        $hits = null;

        if (config('typesense.enabled')) {
            try {
                $hits = $this->typesenseDocs($restaurant, $query, $limit);
            } catch (\Throwable $e) {
                Log::warning('Typesense search failed, using the database instead', ['error' => $e->getMessage()]);
            }
        }

        if ($hits === null) {
            $engine = 'sql_keyword';
            $hits = $this->databaseDocs($restaurant, $query, $limit);
        }

        return ['engine' => $engine, 'query' => $query, 'hits' => $hits];
    }

    // Builds the Typesense filter, e.g. "restaurant_id:=1 && price:<=25 && allergens:!=[`peanuts`]"
    public function buildFilterBy(int $restaurantId, DishFilters $filters): string
    {
        $parts = ["restaurant_id:={$restaurantId}"];

        if ($filters->onlyAvailable) {
            $parts[] = 'available:=true';
        }
        if ($filters->category) {
            $parts[] = 'category_slug:='.$this->escape($filters->category);
        }
        if ($filters->minPrice !== null) {
            $parts[] = 'price:>='.$filters->minPrice;
        }
        if ($filters->maxPrice !== null) {
            $parts[] = 'price:<='.$filters->maxPrice;
        }
        if ($filters->vegetarian !== null) {
            $parts[] = 'is_vegetarian:='.($filters->vegetarian ? 'true' : 'false');
        }
        if ($filters->vegan !== null) {
            $parts[] = 'is_vegan:='.($filters->vegan ? 'true' : 'false');
        }
        if ($filters->maxSpicy !== null) {
            $parts[] = 'spicy_level:<='.$filters->maxSpicy;
        }
        if (count($filters->excludeAllergens) > 0) {
            $allergens = array_map(fn ($slug) => $this->escape($slug), $filters->excludeAllergens);
            $parts[] = 'allergens:!=['.implode(',', $allergens).']';
        }

        return implode(' && ', $parts);
    }

    private function typesenseDishes(Restaurant $restaurant, string $query, DishFilters $filters, int $limit): array
    {
        $params = [
            'q' => '*',
            'query_by' => 'name',
            'filter_by' => $this->buildFilterBy($restaurant->id, $filters),
            'exclude_fields' => 'embedding',
            'per_page' => $limit,
        ];

        // with a real query we search on the text fields AND the embedding (= hybrid search)
        if (trim($query) !== '' && trim($query) !== '*') {
            $params['q'] = $query;
            $params['query_by'] = 'name,description,category,embedding';
            $params['vector_query'] = 'embedding:([], alpha: '.config('typesense.hybrid_alpha').')';
            $params['prioritize_exact_match'] = 'true';
            $params['drop_tokens_threshold'] = 2;
        }

        $result = $this->client->search(config('typesense.collections.dishes'), $params);
        $hits = [];

        foreach ($result['hits'] ?? [] as $hit) {
            $doc = $hit['document'];
            $hits[] = [
                'id' => (int) $doc['id'],
                'name' => $doc['name'],
                'description' => $doc['description'],
                'category' => $doc['category'] ?? null,
                'price' => (float) $doc['price'],
                'currency' => 'TND',
                'is_vegetarian' => $doc['is_vegetarian'],
                'is_vegan' => $doc['is_vegan'],
                'spicy_level' => $doc['spicy_level'],
                'available' => $doc['available'],
                'allergens' => $doc['allergens'],
                'score' => $this->score($hit),
            ];
        }

        return $hits;
    }

    private function typesenseDocs(Restaurant $restaurant, string $query, int $limit): array
    {
        $result = $this->client->search(config('typesense.collections.doc_chunks'), [
            'q' => $query,
            'query_by' => 'content,heading,document_title,embedding',
            'vector_query' => 'embedding:([], alpha: '.config('typesense.hybrid_alpha').')',
            'filter_by' => "restaurant_id:={$restaurant->id}",
            'exclude_fields' => 'embedding',
            'per_page' => $limit,
            'drop_tokens_threshold' => 2,
        ]);

        $hits = [];

        foreach ($result['hits'] ?? [] as $hit) {
            $doc = $hit['document'];
            $hits[] = [
                'chunk_id' => $doc['id'],
                'document_id' => $doc['document_id'],
                'document_title' => $doc['document_title'],
                'heading' => $doc['heading'] ?: null,
                'content' => $doc['content'],
                'score' => $this->score($hit),
            ];
        }

        return $hits;
    }

    private function databaseDishes(Restaurant $restaurant, string $query, DishFilters $filters, int $limit): array
    {
        $dishes = $this->dishes->filter($restaurant->id, $filters);
        $words = $this->keywords($query, $filters);

        $matching = $dishes->filter(function (Dish $dish) use ($words) {
            $text = $dish->name.' '.$dish->description.' '.$dish->category?->name;

            return $this->countMatches($words, $text) > 0;
        });

        // a query like "vegetarian under 25 TND" has no real keywords, so the filters decide alone
        if ($matching->isNotEmpty()) {
            $dishes = $matching;
        }

        return $dishes->take($limit)->map(fn (Dish $dish) => [
            'id' => $dish->id,
            'name' => $dish->name,
            'description' => $dish->description,
            'category' => $dish->category?->name,
            'price' => (float) $dish->price,
            'currency' => 'TND',
            'is_vegetarian' => $dish->is_vegetarian,
            'is_vegan' => $dish->is_vegan,
            'spicy_level' => $dish->spicy_level,
            'available' => $dish->available,
            'allergens' => $dish->allergens->pluck('slug')->all(),
            'score' => null,
        ])->values()->all();
    }

    private function databaseDocs(Restaurant $restaurant, string $query, int $limit): array
    {
        $words = $this->keywords($query, new DishFilters);

        $chunks = DocumentChunk::with('document')
            ->whereHas('document', fn ($q) => $q->where('restaurant_id', $restaurant->id))
            ->get();

        $results = [];

        foreach ($chunks as $chunk) {
            $matches = $this->countMatches($words, $chunk->heading.' '.$chunk->content);

            if ($matches > 0) {
                $results[] = [
                    'chunk_id' => $chunk->document_id.'-'.$chunk->position,
                    'document_id' => $chunk->document_id,
                    'document_title' => $chunk->document->title,
                    'heading' => $chunk->heading,
                    'content' => $chunk->content,
                    'score' => $matches,
                ];
            }
        }

        // the chunks with the most matching words first
        usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($results, 0, $limit);
    }

    private function keywords(string $query, DishFilters $filters): array
    {
        $words = preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($query)), -1, PREG_SPLIT_NO_EMPTY);
        $keywords = [];

        foreach ($words as $word) {
            if (strlen($word) < 4 || in_array($word, self::IGNORED_WORDS)) {
                continue;
            }

            // in "allergic to peanuts", "peanuts" is a filter and not something to search for
            if (count(array_intersect(AllergenResolver::resolve($word), $filters->excludeAllergens)) > 0) {
                continue;
            }

            $keywords[] = $word;
        }

        return array_unique($keywords);
    }

    private function countMatches(array $words, string $text): int
    {
        $text = Str::lower(Str::ascii($text));
        $count = 0;

        foreach ($words as $word) {
            if (str_contains($text, $word)) {
                $count++;
            }
        }

        return $count;
    }

    private function score(array $hit): float
    {
        $score = $hit['hybrid_search_info']['rank_fusion_score'] ?? $hit['text_match'] ?? 0;

        return round((float) $score, 4);
    }

    private function escape(string $value): string
    {
        return '`'.str_replace('`', '', $value).'`';
    }
}
