<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

/**
 * OpenAPI description of the MenuMind REST API, rendered by l5-swagger at
 * /api/documentation. Kept in one place instead of on the controllers so the
 * controllers stay readable.
 */
#[OA\Info(
    version: '1.0.0',
    title: 'MenuMind API',
    description: 'REST API behind MenuMind, the AI assistant for restaurants. All errors share the envelope `{ "error": { "status", "code", "message", "details?" } }`. Prices are in Tunisian dinars (TND).',
)]
#[OA\Server(url: L5_SWAGGER_CONST_HOST, description: 'MenuMind API')]
#[OA\SecurityScheme(securityScheme: 'sanctum', type: 'http', scheme: 'bearer', bearerFormat: 'Sanctum token', description: 'Token returned by /api/auth/login')]
#[OA\Tag(name: 'Auth', description: 'Registration and Sanctum tokens')]
#[OA\Tag(name: 'Menu', description: 'Public menu, filters and allergen checks')]
#[OA\Tag(name: 'Search', description: 'Hybrid (keyword + vector) search over dishes and documents')]
#[OA\Tag(name: 'Orders', description: 'Customer orders')]
#[OA\Tag(name: 'Chat', description: 'Conversational assistant (proxied to the agent service)')]
#[OA\Tag(name: 'Admin', description: 'Back-office: dishes, documents, reindexing')]
#[OA\Schema(
    schema: 'Error',
    properties: [
        new OA\Property(property: 'error', properties: [
            new OA\Property(property: 'status', type: 'integer', example: 422),
            new OA\Property(property: 'code', type: 'string', example: 'validation_failed'),
            new OA\Property(property: 'message', type: 'string', example: 'The price field is required.'),
            new OA\Property(property: 'details', type: 'object', example: ['price' => ['The price field is required.']]),
        ], type: 'object'),
    ],
)]
#[OA\Schema(
    schema: 'User',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 2),
        new OA\Property(property: 'name', type: 'string', example: 'Yasmine Ben Ali'),
        new OA\Property(property: 'email', type: 'string', example: 'demo@menumind.tn'),
        new OA\Property(property: 'role', type: 'string', enum: ['admin', 'customer']),
    ],
)]
#[OA\Schema(
    schema: 'Allergen',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 5),
        new OA\Property(property: 'name', type: 'string', example: 'Peanuts'),
        new OA\Property(property: 'slug', type: 'string', example: 'peanuts'),
        new OA\Property(property: 'icon', type: 'string', example: '🥜'),
    ],
)]
#[OA\Schema(
    schema: 'Dish',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 6),
        new OA\Property(property: 'name', type: 'string', example: 'Ojja Légumes'),
        new OA\Property(property: 'slug', type: 'string', example: 'ojja-legumes'),
        new OA\Property(property: 'description', type: 'string'),
        new OA\Property(property: 'price', type: 'number', format: 'float', example: 11.5),
        new OA\Property(property: 'currency', type: 'string', example: 'TND'),
        new OA\Property(property: 'is_vegetarian', type: 'boolean'),
        new OA\Property(property: 'is_vegan', type: 'boolean'),
        new OA\Property(property: 'spicy_level', type: 'integer', minimum: 0, maximum: 3),
        new OA\Property(property: 'available', type: 'boolean'),
        new OA\Property(property: 'category', properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string', example: 'Starters'),
            new OA\Property(property: 'slug', type: 'string', example: 'starters'),
        ], type: 'object'),
        new OA\Property(property: 'allergens', type: 'array', items: new OA\Items(ref: '#/components/schemas/Allergen')),
    ],
)]
#[OA\Schema(
    schema: 'DishInput',
    required: ['name', 'description', 'price'],
    properties: [
        new OA\Property(property: 'name', type: 'string', example: 'Brik au Thon'),
        new OA\Property(property: 'description', type: 'string', example: 'Crispy pastry with tuna and egg.'),
        new OA\Property(property: 'price', type: 'number', example: 6.5),
        new OA\Property(property: 'category_id', type: 'integer', example: 1),
        new OA\Property(property: 'is_vegetarian', type: 'boolean'),
        new OA\Property(property: 'is_vegan', type: 'boolean'),
        new OA\Property(property: 'spicy_level', type: 'integer', example: 1),
        new OA\Property(property: 'available', type: 'boolean'),
        new OA\Property(property: 'allergens', type: 'array', items: new OA\Items(type: 'string'), example: ['gluten', 'eggs', 'fish']),
    ],
)]
#[OA\Schema(
    schema: 'Order',
    properties: [
        new OA\Property(property: 'id', type: 'integer', example: 12),
        new OA\Property(property: 'status', type: 'string', enum: ['pending', 'confirmed', 'preparing', 'ready', 'delivered', 'cancelled']),
        new OA\Property(property: 'status_label', type: 'string', example: 'Being prepared'),
        new OA\Property(property: 'total', type: 'number', example: 29.5),
        new OA\Property(property: 'currency', type: 'string', example: 'TND'),
        new OA\Property(property: 'notes', type: 'string', nullable: true),
        new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'dish_id', type: 'integer'),
            new OA\Property(property: 'dish_name', type: 'string'),
            new OA\Property(property: 'quantity', type: 'integer'),
            new OA\Property(property: 'unit_price', type: 'number'),
            new OA\Property(property: 'subtotal', type: 'number'),
        ])),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
#[OA\Schema(
    schema: 'Document',
    properties: [
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'title', type: 'string', example: 'Allergen & Dietary Policy'),
        new OA\Property(property: 'original_filename', type: 'string'),
        new OA\Property(property: 'chunks_count', type: 'integer', example: 9),
        new OA\Property(property: 'indexed', type: 'boolean'),
    ],
)]
#[OA\Schema(
    schema: 'DishSearchResult',
    properties: [
        new OA\Property(property: 'engine', type: 'string', enum: ['typesense_hybrid', 'sql_keyword']),
        new OA\Property(property: 'query', type: 'string'),
        new OA\Property(property: 'filters', type: 'object'),
        new OA\Property(property: 'hits', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'id', type: 'integer'),
            new OA\Property(property: 'name', type: 'string'),
            new OA\Property(property: 'price', type: 'number'),
            new OA\Property(property: 'allergens', type: 'array', items: new OA\Items(type: 'string')),
            new OA\Property(property: 'score', type: 'number'),
        ])),
    ],
)]
#[OA\Schema(
    schema: 'DocSearchResult',
    properties: [
        new OA\Property(property: 'engine', type: 'string'),
        new OA\Property(property: 'hits', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'chunk_id', type: 'string', example: '1-2'),
            new OA\Property(property: 'document_title', type: 'string'),
            new OA\Property(property: 'heading', type: 'string'),
            new OA\Property(property: 'content', type: 'string'),
            new OA\Property(property: 'score', type: 'number'),
        ])),
    ],
)]
#[OA\Response(response: 'ValidationError', description: 'Validation failed', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'Unauthenticated', description: 'Missing or invalid token', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'Forbidden', description: 'Admin role required', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
#[OA\Response(response: 'NotFound', description: 'Resource not found', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
final class OpenApiSpec
{
    // ---------------------------------------------------------------- Auth

    #[OA\Post(path: '/api/auth/register', summary: 'Register a customer account', tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'email', 'password', 'password_confirmation'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Sami Ben Salah'),
            new OA\Property(property: 'email', type: 'string', example: 'sami@example.tn'),
            new OA\Property(property: 'password', type: 'string', example: 'secret123'),
            new OA\Property(property: 'password_confirmation', type: 'string', example: 'secret123'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Account created, token issued'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ])]
    public function register(): void {}

    #[OA\Post(path: '/api/auth/login', summary: 'Exchange credentials for a Sanctum token', tags: ['Auth'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'email', type: 'string', example: 'demo@menumind.tn'),
            new OA\Property(property: 'password', type: 'string', example: 'password'),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Token issued', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'user', ref: '#/components/schemas/User'),
                    new OA\Property(property: 'token', type: 'string', example: '1|x8Yq...'),
                    new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
                ], type: 'object'),
            ])),
            new OA\Response(response: 401, description: 'Invalid credentials', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ])]
    public function login(): void {}

    #[OA\Get(path: '/api/auth/me', summary: 'Current user', security: [['sanctum' => []]], tags: ['Auth'],
        responses: [
            new OA\Response(response: 200, description: 'The authenticated user', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/User')])),
            new OA\Response(response: 401, ref: '#/components/responses/Unauthenticated'),
        ])]
    public function me(): void {}

    #[OA\Post(path: '/api/auth/logout', summary: 'Revoke the current token', security: [['sanctum' => []]], tags: ['Auth'],
        responses: [new OA\Response(response: 204, description: 'Logged out')])]
    public function logout(): void {}

    // ---------------------------------------------------------------- Menu

    #[OA\Get(path: '/api/restaurant', summary: 'Restaurant details', tags: ['Menu'],
        responses: [new OA\Response(response: 200, description: 'Restaurant')])]
    public function restaurant(): void {}

    #[OA\Get(path: '/api/categories', summary: 'Menu categories with dish counts', tags: ['Menu'],
        responses: [new OA\Response(response: 200, description: 'Categories')])]
    public function categories(): void {}

    #[OA\Get(path: '/api/allergens', summary: 'Tracked allergens', tags: ['Menu'],
        responses: [new OA\Response(response: 200, description: 'Allergens', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Allergen')),
        ]))])]
    public function allergens(): void {}

    #[OA\Get(path: '/api/dishes', summary: 'List and filter dishes (paginated)', tags: ['Menu'],
        parameters: [
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', description: 'Category slug or id', schema: new OA\Schema(type: 'string', example: 'mains')),
            new OA\Parameter(name: 'min_price', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'max_price', in: 'query', schema: new OA\Schema(type: 'number', example: 25)),
            new OA\Parameter(name: 'vegetarian', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'vegan', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'max_spicy', in: 'query', schema: new OA\Schema(type: 'integer', maximum: 3)),
            new OA\Parameter(name: 'exclude_allergens', in: 'query', description: 'Comma separated slugs or synonyms (e.g. "peanut,milk")', schema: new OA\Schema(type: 'string', example: 'peanuts')),
            new OA\Parameter(name: 'sort', in: 'query', schema: new OA\Schema(type: 'string', enum: ['menu', 'name', 'price_asc', 'price_desc', 'newest', 'spicy'], default: 'menu')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 12)),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Paginated dishes', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Dish')),
                new OA\Property(property: 'meta', type: 'object'),
                new OA\Property(property: 'links', type: 'object'),
            ])),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ])]
    public function dishes(): void {}

    #[OA\Get(path: '/api/dishes/{id}', summary: 'Dish details', tags: ['Menu'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Dish', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Dish')])),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ])]
    public function dish(): void {}

    #[OA\Post(path: '/api/dishes/allergen-check', summary: 'Check dishes against a list of allergies', tags: ['Menu'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'dish_ids', type: 'array', items: new OA\Items(type: 'integer'), example: [6, 30]),
            new OA\Property(property: 'allergens', type: 'array', items: new OA\Items(type: 'string'), example: ['peanut']),
        ])),
        responses: [new OA\Response(response: 200, description: 'Per-dish verdict with conflicting allergens')])]
    public function allergenCheck(): void {}

    // -------------------------------------------------------------- Search

    #[OA\Get(path: '/api/search/dishes', summary: 'Hybrid semantic + keyword dish search', description: 'Typesense hybrid search (BM25 + all-MiniLM-L12-v2 embeddings, rank fusion). Accepts the same filters as /api/dishes. Falls back to SQL keyword scoring when Typesense is unavailable.', tags: ['Search'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', schema: new OA\Schema(type: 'string', example: 'something warm and spicy without meat')),
            new OA\Parameter(name: 'vegetarian', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'max_price', in: 'query', schema: new OA\Schema(type: 'number')),
            new OA\Parameter(name: 'exclude_allergens', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 8)),
        ],
        responses: [new OA\Response(response: 200, description: 'Ranked hits', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/DishSearchResult')]))])]
    public function searchDishes(): void {}

    #[OA\Get(path: '/api/search/docs', summary: 'Retrieve document chunks (RAG)', tags: ['Search'],
        parameters: [
            new OA\Parameter(name: 'q', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'how much is delivery to Carthage?')),
            new OA\Parameter(name: 'limit', in: 'query', schema: new OA\Schema(type: 'integer', default: 4)),
        ],
        responses: [new OA\Response(response: 200, description: 'Ranked chunks with citations', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/DocSearchResult')]))])]
    public function searchDocs(): void {}

    // -------------------------------------------------------------- Orders

    #[OA\Get(path: '/api/orders', summary: 'Order history (own orders, or all for admins)', security: [['sanctum' => []]], tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'status', in: 'query', schema: new OA\Schema(type: 'string'))],
        responses: [new OA\Response(response: 200, description: 'Paginated orders', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Order')),
        ]))])]
    public function orders(): void {}

    #[OA\Post(path: '/api/orders', summary: 'Place an order', security: [['sanctum' => []]], tags: ['Orders'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'items', type: 'array', items: new OA\Items(properties: [
                new OA\Property(property: 'dish_id', type: 'integer', example: 6),
                new OA\Property(property: 'quantity', type: 'integer', example: 2),
            ])),
            new OA\Property(property: 'notes', type: 'string', example: 'No onions please'),
        ])),
        responses: [
            new OA\Response(response: 201, description: 'Order created (total computed server side)', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 422, description: 'Validation failed, unknown or unavailable dish', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ])]
    public function placeOrder(): void {}

    #[OA\Get(path: '/api/orders/{id}', summary: 'Order details and status', security: [['sanctum' => []]], tags: ['Orders'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [
            new OA\Response(response: 200, description: 'Order', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Order')])),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 404, ref: '#/components/responses/NotFound'),
        ])]
    public function order(): void {}

    #[OA\Patch(path: '/api/orders/{id}/status', summary: 'Move an order to its next status', security: [['sanctum' => []]], tags: ['Admin'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [new OA\Property(property: 'status', type: 'string', example: 'preparing')])),
        responses: [
            new OA\Response(response: 200, description: 'Updated order'),
            new OA\Response(response: 409, description: 'Transition not allowed', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ])]
    public function orderStatus(): void {}

    // ---------------------------------------------------------------- Chat

    #[OA\Post(path: '/api/chat', summary: 'Talk to the MenuMind assistant', description: 'Relays the message, the user identity and the caller token to the LangGraph agent. With `stream=true` (default) the response is `text/event-stream` with `step`, `token`, `citations`, `done` and `error` events.', security: [['sanctum' => []]], tags: ['Chat'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(properties: [
            new OA\Property(property: 'message', type: 'string', example: "I'm allergic to peanuts, what vegetarian dishes can I order under 25 TND?"),
            new OA\Property(property: 'session_id', type: 'string', example: 'b6f1c7e2'),
            new OA\Property(property: 'stream', type: 'boolean', example: false),
        ])),
        responses: [
            new OA\Response(response: 200, description: 'Answer with tool steps and citations (JSON) or an SSE stream'),
            new OA\Response(response: 503, description: 'Agent unavailable', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ])]
    public function chat(): void {}

    // --------------------------------------------------------------- Admin

    #[OA\Post(path: '/api/dishes', summary: 'Create a dish', security: [['sanctum' => []]], tags: ['Admin'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: '#/components/schemas/DishInput')),
        responses: [
            new OA\Response(response: 201, description: 'Created'),
            new OA\Response(response: 403, ref: '#/components/responses/Forbidden'),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ])]
    public function createDish(): void {}

    #[OA\Patch(path: '/api/dishes/{id}', summary: 'Update a dish (partial)', security: [['sanctum' => []]], tags: ['Admin'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        requestBody: new OA\RequestBody(content: new OA\JsonContent(ref: '#/components/schemas/DishInput')),
        responses: [new OA\Response(response: 200, description: 'Updated')])]
    public function updateDish(): void {}

    #[OA\Delete(path: '/api/dishes/{id}', summary: 'Delete a dish', security: [['sanctum' => []]], tags: ['Admin'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')])]
    public function deleteDish(): void {}

    #[OA\Get(path: '/api/documents', summary: 'Knowledge base documents', security: [['sanctum' => []]], tags: ['Admin'],
        responses: [new OA\Response(response: 200, description: 'Documents', content: new OA\JsonContent(properties: [
            new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Document')),
        ]))])]
    public function documents(): void {}

    #[OA\Post(path: '/api/documents', summary: 'Upload a document (txt, md, pdf) to the RAG knowledge base', security: [['sanctum' => []]], tags: ['Admin'],
        requestBody: new OA\RequestBody(required: true, content: new OA\MediaType(mediaType: 'multipart/form-data', schema: new OA\Schema(required: ['file'], properties: [
            new OA\Property(property: 'file', type: 'string', format: 'binary'),
            new OA\Property(property: 'title', type: 'string'),
        ]))),
        responses: [
            new OA\Response(response: 201, description: 'Extracted, chunked and indexed', content: new OA\JsonContent(properties: [new OA\Property(property: 'data', ref: '#/components/schemas/Document')])),
            new OA\Response(response: 422, ref: '#/components/responses/ValidationError'),
        ])]
    public function uploadDocument(): void {}

    #[OA\Delete(path: '/api/documents/{id}', summary: 'Delete a document and its chunks', security: [['sanctum' => []]], tags: ['Admin'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer'))],
        responses: [new OA\Response(response: 204, description: 'Deleted')])]
    public function deleteDocument(): void {}

    #[OA\Get(path: '/api/admin/stats', summary: 'Dashboard counters and search engine health', security: [['sanctum' => []]], tags: ['Admin'],
        responses: [new OA\Response(response: 200, description: 'Stats')])]
    public function stats(): void {}

    #[OA\Post(path: '/api/admin/reindex', summary: 'Rebuild the Typesense collections', security: [['sanctum' => []]], tags: ['Admin'],
        responses: [
            new OA\Response(response: 200, description: 'Reindexed counts'),
            new OA\Response(response: 503, description: 'Typesense unreachable', content: new OA\JsonContent(ref: '#/components/schemas/Error')),
        ])]
    public function reindex(): void {}
}
