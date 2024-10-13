<?php

namespace App\Support;

/**
 * Immutable value object describing a menu query. Shared by the SQL
 * repository and the Typesense search so both apply identical rules.
 */
final readonly class DishFilters
{
    /**
     * @param  list<string>  $excludeAllergens  allergen slugs that must NOT be present
     */
    public function __construct(
        public ?string $search = null,
        public ?string $category = null,
        public ?float $minPrice = null,
        public ?float $maxPrice = null,
        public ?bool $vegetarian = null,
        public ?bool $vegan = null,
        public ?int $maxSpicy = null,
        public array $excludeAllergens = [],
        public bool $onlyAvailable = true,
        public string $sort = 'name',
        public int $perPage = 12,
    ) {}

    /**
     * @param  array<string, mixed>  $input  validated request input
     */
    public static function fromArray(array $input): self
    {
        $bool = static fn ($value): ?bool => $value === null ? null : filter_var($value, FILTER_VALIDATE_BOOL);

        return new self(
            search: isset($input['search']) && trim((string) $input['search']) !== '' ? trim((string) $input['search']) : null,
            category: $input['category'] ?? null,
            minPrice: isset($input['min_price']) ? (float) $input['min_price'] : null,
            maxPrice: isset($input['max_price']) ? (float) $input['max_price'] : null,
            vegetarian: $bool($input['vegetarian'] ?? null),
            vegan: $bool($input['vegan'] ?? null),
            maxSpicy: isset($input['max_spicy']) ? (int) $input['max_spicy'] : null,
            excludeAllergens: AllergenResolver::resolve($input['exclude_allergens'] ?? []),
            onlyAvailable: $bool($input['available'] ?? true) ?? true,
            sort: $input['sort'] ?? 'name',
            perPage: (int) ($input['per_page'] ?? 12),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return array_filter([
            'search' => $this->search,
            'category' => $this->category,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'vegetarian' => $this->vegetarian,
            'vegan' => $this->vegan,
            'max_spicy' => $this->maxSpicy,
            'exclude_allergens' => $this->excludeAllergens ?: null,
        ], fn ($v) => $v !== null);
    }
}
