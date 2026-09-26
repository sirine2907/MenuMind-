<?php

namespace App\Support;

// Holds the menu filters so the SQL query and the Typesense search use the same rules.
class DishFilters
{
    public ?string $search = null;

    public ?string $category = null;

    public ?float $minPrice = null;

    public ?float $maxPrice = null;

    public ?bool $vegetarian = null;

    public ?bool $vegan = null;

    public ?int $maxSpicy = null;

    public array $excludeAllergens = [];

    public bool $onlyAvailable = true;

    public string $sort = 'menu';

    public int $perPage = 12;

    public static function fromArray(array $input): self
    {
        $filters = new self;

        if (! empty($input['search']) && trim($input['search']) !== '') {
            $filters->search = trim($input['search']);
        }

        $filters->category = $input['category'] ?? null;

        if (isset($input['min_price'])) {
            $filters->minPrice = (float) $input['min_price'];
        }

        if (isset($input['max_price'])) {
            $filters->maxPrice = (float) $input['max_price'];
        }

        if (isset($input['vegetarian'])) {
            $filters->vegetarian = filter_var($input['vegetarian'], FILTER_VALIDATE_BOOL);
        }

        if (isset($input['vegan'])) {
            $filters->vegan = filter_var($input['vegan'], FILTER_VALIDATE_BOOL);
        }

        if (isset($input['max_spicy'])) {
            $filters->maxSpicy = (int) $input['max_spicy'];
        }

        if (isset($input['available'])) {
            $filters->onlyAvailable = filter_var($input['available'], FILTER_VALIDATE_BOOL);
        }

        $filters->excludeAllergens = AllergenResolver::resolve($input['exclude_allergens'] ?? []);
        $filters->sort = $input['sort'] ?? 'menu';
        $filters->perPage = (int) ($input['per_page'] ?? 12);

        return $filters;
    }

    // Used to echo back the active filters in API responses
    public function toArray(): array
    {
        $values = [
            'search' => $this->search,
            'category' => $this->category,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'vegetarian' => $this->vegetarian,
            'vegan' => $this->vegan,
            'max_spicy' => $this->maxSpicy,
            'exclude_allergens' => $this->excludeAllergens ?: null,
        ];

        return array_filter($values, fn ($value) => $value !== null);
    }
}
