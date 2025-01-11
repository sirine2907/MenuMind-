<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dish\CheckAllergensRequest;
use App\Http\Requests\Dish\IndexDishRequest;
use App\Http\Requests\Dish\StoreDishRequest;
use App\Http\Requests\Dish\UpdateDishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Services\DishService;
use App\Support\DishFilters;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DishController extends Controller
{
    public function __construct(private readonly DishService $dishes) {}

    public function index(IndexDishRequest $request): AnonymousResourceCollection
    {
        $filters = DishFilters::fromArray($request->validated());

        return DishResource::collection($this->dishes->list(Restaurant::current(), $filters))
            ->additional(['meta' => ['filters' => $filters->toArray()]]);
    }

    public function show(Dish $dish): DishResource
    {
        return new DishResource($dish->load(['category', 'allergens']));
    }

    public function store(StoreDishRequest $request): JsonResponse
    {
        $dish = $this->dishes->create(Restaurant::current(), $request->validated());

        return (new DishResource($dish))->response()->setStatusCode(201);
    }

    public function update(UpdateDishRequest $request, Dish $dish): DishResource
    {
        return new DishResource($this->dishes->update($dish, $request->validated()));
    }

    public function destroy(Dish $dish): Response
    {
        $this->dishes->delete($dish);

        return response()->noContent();
    }

    public function checkAllergens(CheckAllergensRequest $request): JsonResponse
    {
        $result = $this->dishes->checkAllergens(
            array_map('intval', $request->validated('dish_ids')),
            $request->validated('allergens'),
        );

        return response()->json(['data' => $result]);
    }
}
