<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Restaurant extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'slug', 'description', 'address', 'phone', 'currency'];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('position');
    }

    public function dishes(): HasMany
    {
        return $this->hasMany(Dish::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    // There is only one restaurant for now, so the "current" one is the first one
    public static function current(): self
    {
        return static::orderBy('id')->firstOrFail();
    }
}
