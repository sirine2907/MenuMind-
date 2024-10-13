<?php

namespace App\Http\Requests\Dish;

class UpdateDishRequest extends StoreDishRequest
{
    /**
     * Same rules as creation, but every field becomes optional (PATCH semantics).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules) => array_map(fn ($rule) => $rule === 'required' ? 'sometimes' : $rule, $rules))
            ->all();
    }
}
