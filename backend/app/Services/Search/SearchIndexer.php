<?php

namespace App\Services\Search;

use App\Models\Dish;
use App\Models\Document;
use App\Repositories\Contracts\DishRepository;
use Illuminate\Support\Facades\Log;

/**
 * Keeps the Typesense collections in sync with the SQL source of truth.
 *
 * Single-record writes (observer driven) never throw: a search index that
 * is temporarily down must not break the admin's CRUD. Bulk reindexing does
 * throw so the artisan command can report the failure.
 */
class SearchIndexer
{
    public function __construct(
        private readonly TypesenseClient $client,
        private readonly DishRepository $dishes,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('typesense.enabled');
    }

    public function indexDish(Dish $dish): void
    {
        $this->safely('index dish '.$dish->id, function () use ($dish) {
            $dish->loadMissing(['category', 'allergens']);
            $this->client->upsert($this->dishCollection(), $this->dishDocument($dish));
        });
    }

    public function removeDish(int $dishId): void
    {
        $this->safely('remove dish '.$dishId, fn () => $this->client->delete($this->dishCollection(), (string) $dishId));
    }

    /**
     * @return bool whether the document ended up in the index
     */
    public function indexDocument(Document $document): bool
    {
        return $this->safely('index document '.$document->id, function () use ($document) {
            $this->client->deleteByFilter($this->chunkCollection(), "document_id:={$document->id}");
            $this->client->import($this->chunkCollection(), $this->chunkDocuments($document));
        });
    }

    public function removeDocument(int $documentId): void
    {
        $this->safely('remove document '.$documentId, fn () => $this->client->deleteByFilter(
            $this->chunkCollection(),
            "document_id:={$documentId}",
        ));
    }

    /**
     * Drops (optionally) and rebuilds both collections from SQL.
     *
     * @return array{dishes: int, doc_chunks: int}
     */
    public function reindexAll(bool $fresh = true): array
    {
        $this->ensureCollection(CollectionSchemas::dishes(), $fresh);
        $this->ensureCollection(CollectionSchemas::docChunks(), $fresh);

        $dishDocs = $this->dishes->allForIndexing()->map(fn (Dish $d) => $this->dishDocument($d))->all();
        $dishCount = $this->client->import($this->dishCollection(), $dishDocs);

        $chunkCount = 0;
        Document::query()->with('chunks')->each(function (Document $document) use (&$chunkCount) {
            $chunkCount += $this->client->import($this->chunkCollection(), $this->chunkDocuments($document));
            $document->forceFill(['indexed_at' => now()])->saveQuietly();
        });

        return ['dishes' => $dishCount, 'doc_chunks' => $chunkCount];
    }

    /**
     * @param  array<string, mixed>  $schema
     */
    private function ensureCollection(array $schema, bool $fresh): void
    {
        if ($fresh) {
            $this->client->deleteCollection($schema['name']);
        } elseif ($this->client->collectionExists($schema['name'])) {
            return;
        }

        $this->client->createCollection($schema);
    }

    /**
     * @return array<string, mixed>
     */
    public function dishDocument(Dish $dish): array
    {
        return [
            'id' => (string) $dish->id,
            'restaurant_id' => $dish->restaurant_id,
            'name' => $dish->name,
            'description' => $dish->description,
            'category' => $dish->category?->name ?? '',
            'category_slug' => $dish->category?->slug ?? '',
            'price' => (float) $dish->price,
            'is_vegetarian' => (bool) $dish->is_vegetarian,
            'is_vegan' => (bool) $dish->is_vegan,
            'spicy_level' => (int) $dish->spicy_level,
            'available' => (bool) $dish->available,
            'allergens' => $dish->allergens->pluck('slug')->values()->all(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function chunkDocuments(Document $document): array
    {
        return $document->chunks->map(fn ($chunk) => [
            'id' => "{$document->id}-{$chunk->position}",
            'restaurant_id' => $document->restaurant_id,
            'document_id' => $document->id,
            'document_title' => $document->title,
            'position' => $chunk->position,
            'heading' => $chunk->heading ?? '',
            'content' => $chunk->content,
        ])->values()->all();
    }

    private function dishCollection(): string
    {
        return config('typesense.collections.dishes');
    }

    private function chunkCollection(): string
    {
        return config('typesense.collections.doc_chunks');
    }

    private function safely(string $action, callable $callback): bool
    {
        if (! $this->enabled()) {
            return false;
        }

        try {
            $callback();

            return true;
        } catch (\Throwable $e) {
            Log::warning("Typesense: could not {$action}", ['error' => $e->getMessage()]);

            return false;
        }
    }
}
