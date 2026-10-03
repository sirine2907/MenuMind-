<?php

return [

    // If false (or if Typesense is down) the search uses the database instead
    'enabled' => env('TYPESENSE_ENABLED', true),

    'host' => env('TYPESENSE_HOST', 'localhost'),
    'port' => env('TYPESENSE_PORT', 8108),
    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
    'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
    'timeout' => (int) env('TYPESENSE_TIMEOUT', 10),

    // Typesense creates the embeddings itself with this model, no OpenAI key needed
    'embedding_model' => env('TYPESENSE_EMBEDDING_MODEL', 'ts/all-MiniLM-L12-v2'),

    // Weight of the vector search in the hybrid search (0 = keywords only, 1 = vectors only)
    'hybrid_alpha' => (float) env('TYPESENSE_HYBRID_ALPHA', 0.4),

    'collections' => [
        'dishes' => env('TYPESENSE_DISHES_COLLECTION', 'dishes'),
        'doc_chunks' => env('TYPESENSE_DOC_CHUNKS_COLLECTION', 'doc_chunks'),
    ],

];
