<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Dish;
use App\Repositories\Contracts\DishRepository;
use App\Support\DishFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class EloquentDishRepository implements DishRepository
{
    public function paginate(int $restaurantId, DishFilters $filters): LengthAwarePaginator
    {
        $query = Dish::query()
            ->with(['category', 'allergens'])
            ->where('restaurant_id', $restaurantId);

        $this->applyFilters($query, $filters);
        $this->applySort($query, $filters->sort);

        return $query->paginate($filters->perPage)->withQueryString();
    }

    public function filter(int $restaurantId, DishFilters $filters): Collection
    {
        $query = Dish::query()
            ->with(['category', 'allergens'])
            ->where('restaurant_id', $restaurantId);

        $this->applyFilters($query, $filters);

        return $query->orderBy('name')->get();
    }

    public function findMany(array $ids): Collection
    {
        return Dish::query()->with(['category', 'allergens'])->whereIn('id', $ids)->get();
    }

    public function allForIndexing(): Collection
    {
        return Dish::query()->with(['category', 'allergens'])->orderBy('id')->get();
    }

    /**
     * @param  Builder<Dish>  $query
     */
    private function applyFilters(Builder $query, DishFilters $filters): void
    {
        $query
            ->when($filters->onlyAvailable, fn (Builder $q) => $q->where('available', true))
            ->when($filters->search, function (Builder $q, string $search) {
                $like = '%'.mb_strtolower($search).'%';
                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like]));
            })
            ->when($filters->category, fn (Builder $q, string $category) => $q->whereHas(
                'category',
                fn (Builder $c) => is_numeric($category) ? $c->whereKey((int) $category) : $c->where('slug', $category),
            ))
            ->when($filters->minPrice !== null, fn (Builder $q) => $q->where('price', '>=', $filters->minPrice))
            ->when($filters->maxPrice !== null, fn (Builder $q) => $q->where('price', '<=', $filters->maxPrice))
            ->when($filters->vegetarian !== null, fn (Builder $q) => $q->where('is_vegetarian', $filters->vegetarian))
            ->when($filters->vegan !== null, fn (Builder $q) => $q->where('is_vegan', $filters->vegan))
            ->when($filters->maxSpicy !== null, fn (Builder $q) => $q->where('spicy_level', '<=', $filters->maxSpicy))
            ->when($filters->excludeAllergens !== [], fn (Builder $q) => $q->whereDoesntHave(
                'allergens',
                fn (Builder $a) => $a->whereIn('slug', $filters->excludeAllergens),
            ));
    }

    /**
     * @param  Builder<Dish>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            // Same order as the printed menu: starters first, desserts and drinks last.
            'menu' => $query
                ->orderBy(Category::select('position')->whereColumn('categories.id', 'dishes.category_id'))
                ->orderBy('name'),
            'price_asc' => $query->orderBy('price')->orderBy('name'),
            'price_desc' => $query->orderByDesc('price')->orderBy('name'),
            'newest' => $query->latest('id'),
            'spicy' => $query->orderByDesc('spicy_level')->orderBy('name'),
            default => $query->orderBy('name'),
        };
    }
}
