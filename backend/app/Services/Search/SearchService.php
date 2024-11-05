<?php

namespace App\Services\Search;

use App\Models\Dish;
use App\Models\DocumentChunk;
use App\Models\Restaurant;
use App\Repositories\Contracts\DishRepository;
use App\Support\DishFilters;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Hybrid (BM25 keyword + vector) search over dishes and document chunks.
 *
 * Typesense is the primary engine. If it is disabled or unreachable the
 * service degrades to an in-database keyword scorer so the assistant keeps
 * answering, only with less semantic recall. Every response says which
 * engine produced it.
 */
class SearchService
{
    public const ENGINE_HYBRID = 'typesense_hybrid';

    public const ENGINE_FALLBACK = 'sql_keyword';

    private const STOPWORDS = [
        'the', 'and', 'for', 'with', 'what', 'which', 'can', 'you', 'your', 'are', 'have', 'has', 'any',
        'dish', 'dishes', 'food', 'something', 'some', 'want', 'would', 'like', 'please', 'under', 'less',
        'than', 'more', 'menu', 'order', 'give', 'show', 'there', 'that', 'this', 'from', 'about', 'does',
        'tnd', 'dinar', 'dinars', 'cheap', 'i\'m', 'allergic', 'free', 'without', 'not', 'how', 'when',
    ];

    public function __construct(
        private readonly TypesenseClient $client,
        private readonly DishRepository $dishes,
    ) {}

    /**
     * @return array{engine: string, query: string, filters: array<string, mixed>, hits: list<array<string, mixed>>}
     */
    public function searchDishes(Restaurant $restaurant, string $query, DishFilters $filters, int $limit = 8): array
    {
        $hits = $this->attempt(
            fn () => $this->typesenseDishes($restaurant, $query, $filters, $limit),
            fn () => $this->fallbackDishes($restaurant, $query, $filters, $limit),
            $engine,
        );

        return ['engine' => $engine, 'query' => $query, 'filters' => $filters->toArray(), 'hits' => $hits];
    }

    /**
     * @return array{engine: string, query: string, hits: list<array<string, mixed>>}
     */
    public function searchDocs(Restaurant $restaurant, string $query, int $limit = 4): array
    {
        $hits = $this->attempt(
            fn () => $this->typesenseDocs($restaurant, $query, $limit),
            fn () => $this->fallbackDocs($restaurant, $query, $limit),
            $engine,
        );

        return ['engine' => $engine, 'query' => $query, 'hits' => $hits];
    }

    /**
     * Builds a Typesense filter_by expression from the shared filter object.
     */
    public function buildFilterBy(int $restaurantId, DishFilters $filters): string
    {
        $clauses = ["restaurant_id:={$restaurantId}"];

        if ($filters->onlyAvailable) {
            $clauses[] = 'available:=true';
        }
        if ($filters->category) {
            $clauses[] = 'category_slug:='.$this->escape($filters->category);
        }
        if ($filters->minPrice !== null) {
            $clauses[] = 'price:>='.$filters->minPrice;
        }
        if ($filters->maxPrice !== null) {
            $clauses[] = 'price:<='.$filters->maxPrice;
        }
        if ($filters->vegetarian !== null) {
            $clauses[] = 'is_vegetarian:='.($filters->vegetarian ? 'true' : 'false');
        }
        if ($filters->vegan !== null) {
            $clauses[] = 'is_vegan:='.($filters->vegan ? 'true' : 'false');
        }
        if ($filters->maxSpicy !== null) {
            $clauses[] = 'spicy_level:<='.$filters->maxSpicy;
        }
        if ($filters->excludeAllergens !== []) {
            $values = implode(',', array_map(fn ($s) => $this->escape($s), $filters->excludeAllergens));
            $clauses[] = "allergens:!=[{$values}]";
        }

        return implode(' && ', $clauses);
    }

    /**
     * @param  callable(): list<array<string, mixed>>  $primary
     * @param  callable(): list<array<string, mixed>>  $fallback
     * @return list<array<string, mixed>>
     */
    private function attempt(callable $primary, callable $fallback, ?string &$engine): array
    {
        if (config('typesense.enabled')) {
            try {
                $engine = self::ENGINE_HYBRID;

                return $primary();
            } catch (\Throwable $e) {
                Log::warning('Typesense search failed, falling back to SQL', ['error' => $e->getMessage()]);
            }
        }

        $engine = self::ENGINE_FALLBACK;

        return $fallback();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function typesenseDishes(Restaurant $restaurant, string $query, DishFilters $filters, int $limit): array
    {
        $hasQuery = trim($query) !== '' && trim($query) !== '*';

        $result = $this->client->search(config('typesense.collections.dishes'), array_filter([
            'q' => $hasQuery ? $query : '*',
            'query_by' => $hasQuery ? 'name,description,category,embedding' : 'name',
            'vector_query' => $hasQuery ? 'embedding:([], alpha: '.config('typesense.hybrid_alpha').')' : null,
            'filter_by' => $this->buildFilterBy($restaurant->id, $filters),
            'exclude_fields' => 'embedding',
            'per_page' => $limit,
            'prioritize_exact_match' => 'true',
            'drop_tokens_threshold' => 2,
        ]));

        return collect($result['hits'] ?? [])->map(function (array $hit) {
            $doc = $hit['document'];

            return [
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
                'score' => round((float) ($hit['hybrid_search_info']['rank_fusion_score'] ?? $hit['text_match'] ?? 0), 4),
            ];
        })->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
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

        return collect($result['hits'] ?? [])->map(fn (array $hit) => [
            'chunk_id' => $hit['document']['id'],
            'document_id' => $hit['document']['document_id'],
            'document_title' => $hit['document']['document_title'],
            'heading' => $hit['document']['heading'] ?: null,
            'content' => $hit['document']['content'],
            'score' => round((float) ($hit['hybrid_search_info']['rank_fusion_score'] ?? $hit['text_match'] ?? 0), 4),
        ])->values()->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackDishes(Restaurant $restaurant, string $query, DishFilters $filters, int $limit): array
    {
        $candidates = $this->dishes->filter($restaurant->id, $filters);
        $terms = $this->terms($query);

        $scored = $candidates->map(fn (Dish $dish) => [
            'dish' => $dish,
            'score' => $this->score($terms, $dish->name.' '.($dish->category?->name ?? '').' '.$dish->description),
        ]);

        // When the query only expresses constraints ("vegetarian under 25 TND"),
        // nothing matches lexically and the structured filters alone decide.
        if ($terms !== [] && $scored->contains(fn ($row) => $row['score'] > 0)) {
            $scored = $scored->filter(fn ($row) => $row['score'] > 0);
        }

        return $scored
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($row) => [
                'id' => $row['dish']->id,
                'name' => $row['dish']->name,
                'description' => $row['dish']->description,
                'category' => $row['dish']->category?->name,
                'price' => (float) $row['dish']->price,
                'currency' => 'TND',
                'is_vegetarian' => $row['dish']->is_vegetarian,
                'is_vegan' => $row['dish']->is_vegan,
                'spicy_level' => $row['dish']->spicy_level,
                'available' => $row['dish']->available,
                'allergens' => $row['dish']->allergens->pluck('slug')->values()->all(),
                'score' => round($row['score'], 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackDocs(Restaurant $restaurant, string $query, int $limit): array
    {
        $terms = $this->terms($query);

        /** @var Collection<int, DocumentChunk> $chunks */
        $chunks = DocumentChunk::query()
            ->with('document:id,title,restaurant_id')
            ->whereHas('document', fn ($q) => $q->where('restaurant_id', $restaurant->id))
            ->get();

        return $chunks
            ->map(fn (DocumentChunk $chunk) => [
                'chunk' => $chunk,
                'score' => $this->score($terms, ($chunk->heading ?? '').' '.$chunk->content),
            ])
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($row) => [
                'chunk_id' => $row['chunk']->document_id.'-'.$row['chunk']->position,
                'document_id' => $row['chunk']->document_id,
                'document_title' => $row['chunk']->document->title,
                'heading' => $row['chunk']->heading,
                'content' => $row['chunk']->content,
                'score' => round($row['score'], 4),
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function terms(string $query): array
    {
        $words = preg_split('/[^\p{L}\p{N}\']+/u', Str::lower($query), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_unique(array_filter(
            $words,
            fn ($w) => mb_strlen($w) >= 3 && ! is_numeric($w) && ! in_array($w, self::STOPWORDS, true),
        )));
    }

    /**
     * Fraction of query terms found in the text, with light prefix matching
     * so "spicy" matches "spice" and "desserts" matches "dessert".
     *
     * @param  list<string>  $terms
     */
    private function score(array $terms, string $text): float
    {
        if ($terms === []) {
            return 0.0;
        }

        $haystack = Str::lower(Str::ascii($text));
        $hits = 0;

        foreach ($terms as $term) {
            $stem = mb_substr(Str::ascii($term), 0, max(4, mb_strlen($term) - 2));
            if (str_contains($haystack, $stem)) {
                $hits++;
            }
        }

        return $hits / count($terms);
    }

    private function escape(string $value): string
    {
        return '`'.str_replace('`', '', $value).'`';
    }
}
