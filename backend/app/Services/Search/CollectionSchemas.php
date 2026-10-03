<?php

namespace App\Services\Search;

// Typesense collections. The "embedding" field is filled by Typesense itself
// with a built-in model, that's what makes the hybrid search possible.
class CollectionSchemas
{
    public static function dishes(): array
    {
        return [
            'name' => config('typesense.collections.dishes'),
            'fields' => [
                ['name' => 'restaurant_id', 'type' => 'int32', 'facet' => true],
                ['name' => 'name', 'type' => 'string'],
                ['name' => 'description', 'type' => 'string'],
                ['name' => 'category', 'type' => 'string', 'facet' => true, 'optional' => true],
                ['name' => 'category_slug', 'type' => 'string', 'facet' => true, 'optional' => true],
                ['name' => 'price', 'type' => 'float', 'facet' => true],
                ['name' => 'is_vegetarian', 'type' => 'bool', 'facet' => true],
                ['name' => 'is_vegan', 'type' => 'bool', 'facet' => true],
                ['name' => 'spicy_level', 'type' => 'int32', 'facet' => true],
                ['name' => 'available', 'type' => 'bool', 'facet' => true],
                ['name' => 'allergens', 'type' => 'string[]', 'facet' => true],
                self::embeddingField(['name', 'description', 'category']),
            ],
            'default_sorting_field' => 'price',
        ];
    }

    public static function docChunks(): array
    {
        return [
            'name' => config('typesense.collections.doc_chunks'),
            'fields' => [
                ['name' => 'restaurant_id', 'type' => 'int32', 'facet' => true],
                ['name' => 'document_id', 'type' => 'int32', 'facet' => true],
                ['name' => 'document_title', 'type' => 'string', 'facet' => true],
                ['name' => 'position', 'type' => 'int32'],
                ['name' => 'heading', 'type' => 'string', 'optional' => true],
                ['name' => 'content', 'type' => 'string'],
                self::embeddingField(['document_title', 'heading', 'content']),
            ],
            'default_sorting_field' => 'position',
        ];
    }

    private static function embeddingField(array $fromFields): array
    {
        return [
            'name' => 'embedding',
            'type' => 'float[]',
            'embed' => [
                'from' => $fromFields,
                'model_config' => ['model_name' => config('typesense.embedding_model')],
            ],
        ];
    }
}
