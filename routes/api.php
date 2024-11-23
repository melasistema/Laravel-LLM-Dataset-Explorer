<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\ChatBotController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/login', [ AuthController::class, 'login' ]);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('chat', [ChatBotController::class, 'handleQuery']);
});
