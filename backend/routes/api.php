<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DishController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\SearchController;
use Illuminate\Support\Facades\Route;

// Public menu
Route::get('restaurant', [MenuController::class, 'restaurant']);
Route::get('categories', [MenuController::class, 'categories']);
Route::get('allergens', [MenuController::class, 'allergens']);
Route::get('dishes', [DishController::class, 'index']);
Route::get('dishes/{dish}', [DishController::class, 'show'])->whereNumber('dish');
Route::post('dishes/allergen-check', [DishController::class, 'checkAllergens']);

// Hybrid search (used by the menu page and by the agent's tools)
Route::middleware('throttle:120,1')->group(function () {
    Route::get('search/dishes', [SearchController::class, 'dishes']);
    Route::get('search/docs', [SearchController::class, 'docs']);
});

// Authenticated customers
Route::middleware('auth:sanctum')->group(function () {
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show'])->whereNumber('order');
});

// Admin back-office
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus'])->whereNumber('order');

    Route::get('documents', [DocumentController::class, 'index']);
    Route::post('documents', [DocumentController::class, 'store']);
    Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->whereNumber('document');

    Route::get('admin/stats', [AdminController::class, 'stats']);
    Route::post('admin/reindex', [AdminController::class, 'reindex']);
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
