<?php

use App\Http\Controllers\Api\V1\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::prefix('api/v1/auth')->group(function (): void {
    Route::post('/login', [SessionController::class, 'store'])
        ->middleware('throttle:5,1')
        ->name('auth.login');

    Route::post('/logout', [SessionController::class, 'destroy'])
        ->middleware('auth:web')
        ->name('auth.logout');
});
