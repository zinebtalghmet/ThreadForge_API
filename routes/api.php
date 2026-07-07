<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\BlueprintController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\GeneratedPostController;
use App\Http\Controllers\RawContentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public authentication routes
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

/*
|--------------------------------------------------------------------------
| Protected routes (Sanctum Bearer token)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', fn (Request $request) => $request->user());
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // US2 / US3 — Blueprints (Campaigns)
    Route::apiResource('blueprints', BlueprintController::class);

    // US4 / US5 — Raw content submission & async generation
    Route::post('/content/repurpose', [RawContentController::class, 'repurpose']);
    Route::get('/content', [RawContentController::class, 'index']);
    Route::get('/content/{rawContent}', [RawContentController::class, 'show']);

    // US6 — Generated posts lifecycle
    Route::get('/posts', [GeneratedPostController::class, 'index']);
    Route::get('/posts/{generatedPost}', [GeneratedPostController::class, 'show']);
    Route::patch('/posts/{generatedPost}/status', [GeneratedPostController::class, 'updateStatus']);

    // US7 / US8 / US9 — Ghostwriter chat assistant
    Route::post('/posts/{generatedPost}/chat', [ChatController::class, 'chat']);
});
