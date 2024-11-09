<?php

namespace App\Models;

use App\Observers\DishObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[ObservedBy(DishObserver::class)]
class Dish extends Model
{
    use HasFactory;

    protected $fillable = [
        'restaurant_id',
        'category_id',
        'name',
        'slug',
        'description',
        'price',
        'is_vegetarian',
        'is_vegan',
        'spicy_level',
        'available',
        'image_url',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:3',
            'is_vegetarian' => 'boolean',
            'is_vegan' => 'boolean',
            'spicy_level' => 'integer',
            'available' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Restaurant, $this>
     */
    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsToMany<Allergen, $this>
     */
    public function allergens(): BelongsToMany
    {
        return $this->belongsToMany(Allergen::class);
    }
}
