<?php

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Accept both ?exclude_allergens=peanuts,gluten and ?exclude_allergens[]=peanuts
        if (is_string($this->exclude_allergens)) {
            $this->merge([
                'exclude_allergens' => array_values(array_filter(array_map('trim', explode(',', $this->exclude_allergens)))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'min_price' => ['nullable', 'numeric', 'min:0'],
            'max_price' => ['nullable', 'numeric', 'min:0', 'gte:min_price'],
            'vegetarian' => ['nullable', 'boolean'],
            'vegan' => ['nullable', 'boolean'],
            'max_spicy' => ['nullable', 'integer', 'between:0,3'],
            'exclude_allergens' => ['nullable', 'array', 'max:14'],
            'exclude_allergens.*' => ['string', 'max:50'],
            'available' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', 'price_asc', 'price_desc', 'newest', 'spicy'])],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'max_price.gte' => 'The maximum price must be greater than or equal to the minimum price.',
        ];
    }
}
