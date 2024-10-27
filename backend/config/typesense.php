<?php

return [

    /*
    | When disabled (or unreachable) MenuMind keeps working: writes skip the
    | index and searches fall back to SQL keyword matching.
    */
    'enabled' => env('TYPESENSE_ENABLED', true),

    'host' => env('TYPESENSE_HOST', 'localhost'),
    'port' => env('TYPESENSE_PORT', 8108),
    'protocol' => env('TYPESENSE_PROTOCOL', 'http'),
    'api_key' => env('TYPESENSE_API_KEY', 'xyz'),
    'timeout' => (int) env('TYPESENSE_TIMEOUT', 10),

    /*
    | Built-in Typesense embedding model: vectors are computed inside
    | Typesense itself, so no external embedding API (or key) is needed.
    */
    'embedding_model' => env('TYPESENSE_EMBEDDING_MODEL', 'ts/all-MiniLM-L12-v2'),

    /*
    | Weight given to the vector side of hybrid search (0 = keyword only,
    | 1 = semantic only). Typesense fuses both rankings with this weight.
    */
    'hybrid_alpha' => (float) env('TYPESENSE_HYBRID_ALPHA', 0.4),

    'collections' => [
        'dishes' => env('TYPESENSE_DISHES_COLLECTION', 'dishes'),
        'doc_chunks' => env('TYPESENSE_DOC_CHUNKS_COLLECTION', 'doc_chunks'),
    ],

];
