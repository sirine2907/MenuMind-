<?php

namespace App\Services;

use App\Models\Allergen;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Repositories\Contracts\DishRepository;
use App\Support\AllergenResolver;
use App\Support\DishFilters;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DishService
{
    public function __construct(private readonly DishRepository $dishes) {}

    /**
     * @return LengthAwarePaginator<int, Dish>
     */
    public function list(Restaurant $restaurant, DishFilters $filters): LengthAwarePaginator
    {
        return $this->dishes->paginate($restaurant->id, $filters);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Restaurant $restaurant, array $data): Dish
    {
        return DB::transaction(function () use ($restaurant, $data) {
            $dish = new Dish(collect($data)->except('allergens')->all());
            $dish->restaurant_id = $restaurant->id;
            $dish->slug = $this->uniqueSlug($restaurant->id, $data['name']);
            $this->normalizeDietFlags($dish);
            $dish->save();

            $this->syncAllergens($dish, $data['allergens'] ?? []);

            return $dish->load(['category', 'allergens']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Dish $dish, array $data): Dish
    {
        return DB::transaction(function () use ($dish, $data) {
            $dish->fill(collect($data)->except('allergens')->all());

            if ($dish->isDirty('name')) {
                $dish->slug = $this->uniqueSlug($dish->restaurant_id, $dish->name, $dish->id);
            }

            $this->normalizeDietFlags($dish);

            if (array_key_exists('allergens', $data)) {
                $this->syncAllergens($dish, $data['allergens']);
            }

            // Always save so observers re-index the dish even when only allergens changed.
            $dish->touch();

            return $dish->load(['category', 'allergens']);
        });
    }

    public function delete(Dish $dish): void
    {
        $dish->delete();
    }

    /**
     * Checks a set of dishes against a customer's allergies.
     *
     * @param  list<int>  $dishIds
     * @param  list<string>|string  $allergies  free text or slugs
     * @return array{allergens_checked: list<string>, results: list<array<string, mixed>>}
     */
    public function checkAllergens(array $dishIds, array|string $allergies): array
    {
        $slugs = AllergenResolver::resolve($allergies);
        $dishes = $this->dishes->findMany($dishIds)->keyBy('id');

        $results = [];

        foreach ($dishIds as $id) {
            $dish = $dishes->get($id);

            if (! $dish) {
                $results[] = ['dish_id' => $id, 'found' => false];

                continue;
            }

            $dishAllergens = $dish->allergens->pluck('slug')->all();
            $conflicts = array_values(array_intersect($dishAllergens, $slugs));

            $results[] = [
                'dish_id' => $dish->id,
                'found' => true,
                'name' => $dish->name,
                'allergens' => $dishAllergens,
                'conflicts' => $conflicts,
                'safe' => $conflicts === [],
            ];
        }

        return ['allergens_checked' => $slugs, 'results' => $results];
    }

    /**
     * @param  list<int|string>  $allergens  ids or slugs
     */
    private function syncAllergens(Dish $dish, array $allergens): void
    {
        $ids = Allergen::query()
            ->whereIn('id', array_filter($allergens, 'is_int'))
            ->orWhereIn('slug', array_filter($allergens, 'is_string'))
            ->pluck('id');

        $dish->allergens()->sync($ids);
    }

    private function normalizeDietFlags(Dish $dish): void
    {
        // Vegan implies vegetarian; keeps filters truthful even with sloppy input.
        if ($dish->is_vegan) {
            $dish->is_vegetarian = true;
        }
    }

    private function uniqueSlug(int $restaurantId, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $i = 2;

        while (Dish::where('restaurant_id', $restaurantId)
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
