<?php

use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\InventoryMovementController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/products', [ProductController::class, 'index'])
        ->name('api.v1.products.index');

    Route::post('/products', [ProductController::class, 'store'])
        ->middleware('auth:sanctum')
        ->name('api.v1.products.store');

    Route::post(
        '/products/{product}/inventory-movements',
        [InventoryMovementController::class, 'store']
    )
        ->whereNumber('product')
        ->middleware('auth:sanctum')
        ->name('api.v1.inventory-movements.store');

    Route::get('/auth/me', [SessionController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('auth.me');
});
