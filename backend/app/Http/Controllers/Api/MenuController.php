<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AllergenResource;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\RestaurantResource;
use App\Models\Allergen;
use App\Models\Restaurant;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only reference data used to build menu filters.
 */
class MenuController extends Controller
{
    public function restaurant(): RestaurantResource
    {
        return new RestaurantResource(Restaurant::current());
    }

    public function categories(): AnonymousResourceCollection
    {
        $categories = Restaurant::current()->categories()->withCount('dishes')->get();

        return CategoryResource::collection($categories);
    }

    public function allergens(): AnonymousResourceCollection
    {
        return AllergenResource::collection(Allergen::orderBy('name')->get());
    }
}
