<?php

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;

class StoreDishRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.1', 'max:99999'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'is_vegetarian' => ['sometimes', 'boolean'],
            'is_vegan' => ['sometimes', 'boolean'],
            'spicy_level' => ['sometimes', 'integer', 'between:0,3'],
            'available' => ['sometimes', 'boolean'],
            'image_url' => ['nullable', 'url', 'max:500'],
            'allergens' => ['sometimes', 'array'],
            'allergens.*' => ['string', 'exists:allergens,slug'],
        ];
    }
}
