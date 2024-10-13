<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\MenuController;
use Illuminate\Support\Facades\Route;

// Public menu
Route::get('restaurant', [MenuController::class, 'restaurant']);
Route::get('categories', [MenuController::class, 'categories']);
Route::get('allergens', [MenuController::class, 'allergens']);
Route::get('dishes', [DishController::class, 'index']);
Route::get('dishes/{dish}', [DishController::class, 'show'])->whereNumber('dish');
Route::post('dishes/allergen-check', [DishController::class, 'checkAllergens']);

// Admin back-office
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::post('dishes', [DishController::class, 'store']);
    Route::match(['put', 'patch'], 'dishes/{dish}', [DishController::class, 'update'])->whereNumber('dish');
    Route::delete('dishes/{dish}', [DishController::class, 'destroy'])->whereNumber('dish');
});

Route::prefix('auth')->group(function () {
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('register', [AuthController::class, 'register']);
        Route::post('login', [AuthController::class, 'login']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});
