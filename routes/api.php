<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\SqlController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    Route::post('/auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:5,1');

    Route::group(['middleware' => ['auth:api', 'throttle:60,1']], function() {
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/refresh', [AuthController::class, 'refresh']);
        Route::post('/sql', [SqlController::class, 'ask'])->middleware('llm.rate_limit');
        Route::get('/sql/result/{id}', [SqlController::class, 'result']);
    });

});
