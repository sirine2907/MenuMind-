<?php

namespace App\Http\Requests\Dish;

use Illuminate\Foundation\Http\FormRequest;

class CheckAllergensRequest extends FormRequest
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
            'dish_ids' => ['required', 'array', 'min:1', 'max:50'],
            'dish_ids.*' => ['integer'],
            'allergens' => ['required', 'array', 'min:1', 'max:14'],
            'allergens.*' => ['string', 'max:50'],
        ];
    }
}
