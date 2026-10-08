<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Dish;
use App\Support\DishFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class DishRepository
{
    public function paginate(int $restaurantId, DishFilters $filters): LengthAwarePaginator
    {
        $query = $this->filteredQuery($restaurantId, $filters);
        $this->applySort($query, $filters->sort);

        return $query->paginate($filters->perPage)->withQueryString();
    }

    // Same filters as paginate() but returns all the dishes (used by the search fallback)
    public function filter(int $restaurantId, DishFilters $filters): Collection
    {
        $query = $this->filteredQuery($restaurantId, $filters);
        $this->applySort($query, $filters->sort);

        return $query->get();
    }

    public function findMany(array $ids): Collection
    {
        return Dish::with(['category', 'allergens'])->whereIn('id', $ids)->get();
    }

    public function allForIndexing(): Collection
    {
        return Dish::with(['category', 'allergens'])->orderBy('id')->get();
    }

    private function filteredQuery(int $restaurantId, DishFilters $filters): Builder
    {
        $query = Dish::with(['category', 'allergens'])->where('restaurant_id', $restaurantId);

        if ($filters->onlyAvailable) {
            $query->where('available', true);
        }

        if ($filters->search) {
            $search = '%'.mb_strtolower($filters->search).'%';
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(name) LIKE ?', [$search])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$search]);
            });
        }

        if ($filters->category) {
            $query->whereHas('category', function ($q) use ($filters) {
                if (is_numeric($filters->category)) {
                    $q->where('id', $filters->category);
                } else {
                    $q->where('slug', $filters->category);
                }
            });
        }

        if ($filters->minPrice !== null) {
            $query->where('price', '>=', $filters->minPrice);
        }

        if ($filters->maxPrice !== null) {
            $query->where('price', '<=', $filters->maxPrice);
        }

        if ($filters->vegetarian !== null) {
            $query->where('is_vegetarian', $filters->vegetarian);
        }

        if ($filters->vegan !== null) {
            $query->where('is_vegan', $filters->vegan);
        }

        if ($filters->maxSpicy !== null) {
            $query->where('spicy_level', '<=', $filters->maxSpicy);
        }

        if (count($filters->excludeAllergens) > 0) {
            $query->whereDoesntHave('allergens', function ($q) use ($filters) {
                $q->whereIn('slug', $filters->excludeAllergens);
            });
        }

        return $query;
    }

    private function applySort(Builder $query, string $sort): void
    {
        if ($sort === 'price_asc') {
            $query->orderBy('price');
        } elseif ($sort === 'price_desc') {
            $query->orderByDesc('price');
        } elseif ($sort === 'newest') {
            $query->latest('id');
        } elseif ($sort === 'spicy') {
            $query->orderByDesc('spicy_level');
        } elseif ($sort === 'menu') {
            // same order as the printed menu: starters first, drinks last
            $query->orderBy(Category::select('position')->whereColumn('categories.id', 'dishes.category_id'));
        }

        $query->orderBy('name');
    }
}
