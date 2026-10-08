<?php

namespace App\Providers;

use App\Services\Search\TypesenseClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TypesenseClient::class, function () {
            return TypesenseClient::fromConfig();
        });
    }

    public function boot(): void
    {
        //
    }
}
