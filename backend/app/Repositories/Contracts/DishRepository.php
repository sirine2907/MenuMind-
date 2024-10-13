<?php

namespace App\Repositories\Contracts;

use App\Models\Dish;
use App\Support\DishFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

interface DishRepository
{
    /**
     * @return LengthAwarePaginator<int, Dish>
     */
    public function paginate(int $restaurantId, DishFilters $filters): LengthAwarePaginator;

    /**
     * @param  list<int>  $ids
     * @return Collection<int, Dish>
     */
    public function findMany(array $ids): Collection;

    /**
     * Every dish with the relations needed to build a search document.
     *
     * @return Collection<int, Dish>
     */
    public function allForIndexing(): Collection;
}
