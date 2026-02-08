<?php

use App\Http\Controllers\Api\V1\SecretController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::post('/secrets', [SecretController::class, 'store']);
    Route::get('/secrets/{id}', [SecretController::class, 'show']);
});

// Health check endpoint for Docker
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now()->toIso8601String(),
    ]);
});
