<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Dish;
use App\Models\Document;
use App\Models\Order;
use App\Services\Search\SearchIndexer;
use App\Services\Search\TypesenseClient;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    public function stats(TypesenseClient $typesense): JsonResponse
    {
        $today = Order::query()->whereDate('created_at', today());

        return response()->json(['data' => [
            'dishes' => [
                'total' => Dish::count(),
                'available' => Dish::where('available', true)->count(),
            ],
            'documents' => [
                'total' => Document::count(),
                'chunks' => (int) Document::sum('chunks_count'),
            ],
            'orders' => [
                'total' => Order::count(),
                'today' => (clone $today)->count(),
                'revenue_today' => (float) (clone $today)->where('status', '!=', OrderStatus::Cancelled)->sum('total'),
                'pending' => Order::where('status', OrderStatus::Pending)->count(),
            ],
            'search' => [
                'enabled' => (bool) config('typesense.enabled'),
                'healthy' => config('typesense.enabled') ? $typesense->isHealthy() : false,
                'embedding_model' => config('typesense.embedding_model'),
            ],
        ]]);
    }

    public function reindex(SearchIndexer $indexer, TypesenseClient $typesense): JsonResponse
    {
        if (! config('typesense.enabled') || ! $typesense->isHealthy()) {
            return response()->json(['error' => [
                'status' => 503,
                'code' => 'search_unavailable',
                'message' => 'Typesense is not reachable, search is running on the SQL fallback.',
            ]], 503);
        }

        $started = microtime(true);
        $counts = $indexer->reindexAll();

        return response()->json(['data' => [
            'indexed' => $counts,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]]);
    }
}
