<?php

namespace App\Services;

use App\Models\Allergen;
use App\Models\Dish;
use App\Models\Restaurant;
use App\Repositories\DishRepository;
use App\Support\AllergenResolver;
use App\Support\DishFilters;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DishService
{
    public function __construct(private DishRepository $dishes) {}

    public function list(Restaurant $restaurant, DishFilters $filters)
    {
        return $this->dishes->paginate($restaurant->id, $filters);
    }

    public function create(Restaurant $restaurant, array $data): Dish
    {
        return DB::transaction(function () use ($restaurant, $data) {
            $dish = new Dish($data);
            $dish->restaurant_id = $restaurant->id;
            $dish->slug = $this->uniqueSlug($restaurant->id, $data['name']);

            // a vegan dish is always vegetarian
            if ($dish->is_vegan) {
                $dish->is_vegetarian = true;
            }

            $dish->save();
            $this->syncAllergens($dish, $data['allergens'] ?? []);

            return $dish->load(['category', 'allergens']);
        });
    }

    public function update(Dish $dish, array $data): Dish
    {
        return DB::transaction(function () use ($dish, $data) {
            $dish->fill($data);

            if ($dish->isDirty('name')) {
                $dish->slug = $this->uniqueSlug($dish->restaurant_id, $dish->name, $dish->id);
            }

            if ($dish->is_vegan) {
                $dish->is_vegetarian = true;
            }

            if (array_key_exists('allergens', $data)) {
                $this->syncAllergens($dish, $data['allergens']);
            }

            // touch() saves the dish even if only the allergens changed,
            // so the observer still updates the search index
            $dish->touch();

            return $dish->load(['category', 'allergens']);
        });
    }

    public function delete(Dish $dish): void
    {
        $dish->delete();
    }

    // Checks each dish against the customer's allergies (used by the assistant)
    public function checkAllergens(array $dishIds, $allergies): array
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
                'safe' => count($conflicts) === 0,
            ];
        }

        return ['allergens_checked' => $slugs, 'results' => $results];
    }

    private function syncAllergens(Dish $dish, array $slugs): void
    {
        $ids = Allergen::whereIn('slug', $slugs)->pluck('id');
        $dish->allergens()->sync($ids);
    }

    private function uniqueSlug(int $restaurantId, string $name, ?int $ignoreId = null): string
    {
        $slug = Str::slug($name);
        $original = $slug;
        $counter = 2;

        while ($this->slugExists($restaurantId, $slug, $ignoreId)) {
            $slug = $original.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    private function slugExists(int $restaurantId, string $slug, ?int $ignoreId): bool
    {
        $query = Dish::where('restaurant_id', $restaurantId)->where('slug', $slug);

        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }
}
