<?php

use App\Http\Controllers\Api\V1\Auth\SessionController;
use App\Http\Controllers\Api\V1\ProductController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('/products', [ProductController::class, 'index'])
        ->name('api.v1.products.index');

    Route::get('/auth/me', [SessionController::class, 'show'])
        ->middleware('auth:sanctum')
        ->name('auth.me');
});
