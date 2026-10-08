<?php

namespace App\Services\Search;

use App\Models\Dish;
use App\Models\Document;
use App\Repositories\DishRepository;
use Illuminate\Support\Facades\Log;

// Copies dishes and document chunks from the database into Typesense.
// The database is the source of truth, the index can always be rebuilt.
class SearchIndexer
{
    public function __construct(
        private TypesenseClient $client,
        private DishRepository $dishes,
    ) {}

    public function indexDish(Dish $dish): void
    {
        if (! config('typesense.enabled')) {
            return;
        }

        try {
            $dish->loadMissing(['category', 'allergens']);
            $this->client->upsert(config('typesense.collections.dishes'), $this->dishDocument($dish));
        } catch (\Throwable $e) {
            // the admin should still be able to save a dish when Typesense is down
            Log::warning("Typesense: could not index dish {$dish->id}", ['error' => $e->getMessage()]);
        }
    }

    public function removeDish(int $dishId): void
    {
        if (! config('typesense.enabled')) {
            return;
        }

        try {
            $this->client->delete(config('typesense.collections.dishes'), (string) $dishId);
        } catch (\Throwable $e) {
            Log::warning("Typesense: could not remove dish {$dishId}", ['error' => $e->getMessage()]);
        }
    }

    // Returns true if the document is now in the index
    public function indexDocument(Document $document): bool
    {
        if (! config('typesense.enabled')) {
            return false;
        }

        try {
            $collection = config('typesense.collections.doc_chunks');
            $this->client->deleteByFilter($collection, "document_id:={$document->id}");
            $this->client->import($collection, $this->chunkDocuments($document));

            return true;
        } catch (\Throwable $e) {
            Log::warning("Typesense: could not index document {$document->id}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    public function removeDocument(int $documentId): void
    {
        if (! config('typesense.enabled')) {
            return;
        }

        try {
            $this->client->deleteByFilter(config('typesense.collections.doc_chunks'), "document_id:={$documentId}");
        } catch (\Throwable $e) {
            Log::warning("Typesense: could not remove document {$documentId}", ['error' => $e->getMessage()]);
        }
    }

    // Recreates both collections and indexes everything again (used by menumind:reindex)
    public function reindexAll(bool $fresh = true): array
    {
        $this->createCollection(CollectionSchemas::dishes(), $fresh);
        $this->createCollection(CollectionSchemas::docChunks(), $fresh);

        $dishDocuments = [];
        foreach ($this->dishes->allForIndexing() as $dish) {
            $dishDocuments[] = $this->dishDocument($dish);
        }
        $dishCount = $this->client->import(config('typesense.collections.dishes'), $dishDocuments);

        $chunkCount = 0;
        foreach (Document::with('chunks')->get() as $document) {
            $chunkCount += $this->client->import(config('typesense.collections.doc_chunks'), $this->chunkDocuments($document));
            $document->indexed_at = now();
            $document->saveQuietly();
        }

        return ['dishes' => $dishCount, 'doc_chunks' => $chunkCount];
    }

    public function dishDocument(Dish $dish): array
    {
        return [
            'id' => (string) $dish->id,
            'restaurant_id' => $dish->restaurant_id,
            'name' => $dish->name,
            'description' => $dish->description,
            'category' => $dish->category->name ?? '',
            'category_slug' => $dish->category->slug ?? '',
            'price' => (float) $dish->price,
            'is_vegetarian' => (bool) $dish->is_vegetarian,
            'is_vegan' => (bool) $dish->is_vegan,
            'spicy_level' => (int) $dish->spicy_level,
            'available' => (bool) $dish->available,
            'allergens' => $dish->allergens->pluck('slug')->values()->all(),
        ];
    }

    private function chunkDocuments(Document $document): array
    {
        $documents = [];

        foreach ($document->chunks as $chunk) {
            $documents[] = [
                'id' => $document->id.'-'.$chunk->position,
                'restaurant_id' => $document->restaurant_id,
                'document_id' => $document->id,
                'document_title' => $document->title,
                'position' => $chunk->position,
                'heading' => $chunk->heading ?? '',
                'content' => $chunk->content,
            ];
        }

        return $documents;
    }

    private function createCollection(array $schema, bool $fresh): void
    {
        if ($fresh) {
            $this->client->deleteCollection($schema['name']);
        } elseif ($this->client->collectionExists($schema['name'])) {
            return;
        }

        $this->client->createCollection($schema);
    }
}
