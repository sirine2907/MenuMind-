<?php

namespace App\Http\Requests\Search;

use App\Http\Requests\Dish\IndexDishRequest;

class SearchDishesRequest extends IndexDishRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'q' => ['nullable', 'string', 'max:300'],
            'limit' => ['nullable', 'integer', 'between:1,30'],
        ];
    }
}
