<?php

namespace App\Providers;

use App\Repositories\Contracts\DishRepository;
use App\Repositories\EloquentDishRepository;
use App\Services\Search\TypesenseClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        DishRepository::class => EloquentDishRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(TypesenseClient::class, fn () => TypesenseClient::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
