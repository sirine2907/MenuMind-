<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Search\SearchDishesRequest;
use App\Models\Restaurant;
use App\Services\Search\SearchService;
use App\Support\DishFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __construct(private SearchService $search) {}

    public function dishes(SearchDishesRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $result = $this->search->searchDishes(
            Restaurant::current(),
            (string) ($validated['q'] ?? ''),
            DishFilters::fromArray($validated),
            (int) ($validated['limit'] ?? 8),
        );

        return response()->json(['data' => $result]);
    }

    public function docs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:300'],
            'limit' => ['nullable', 'integer', 'between:1,10'],
        ]);

        $result = $this->search->searchDocs(
            Restaurant::current(),
            $validated['q'],
            (int) ($validated['limit'] ?? 4),
        );

        return response()->json(['data' => $result]);
    }
}
