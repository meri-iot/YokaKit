<?php

use App\Http\Controllers\Api\V1\AndonInfoController;
use App\Http\Controllers\Api\V1\ProcessInfoController;
use App\Http\Controllers\Api\V1\StopProductionController;
use App\Http\Controllers\Api\V1\SwitchPartNumberController;
use App\Http\Controllers\Api\V1\UserInfoController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/


Route::group(['prefix' => 'v1', 'as' => 'production.'], function () {
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/user', UserInfoController::class)->name('user');
        Route::post('/switch-part-number', SwitchPartNumberController::class)->name('switch');
        Route::post('/stop-production', StopProductionController::class)->name('stop');
        Route::get('/processes', ProcessInfoController::class)->name('processes');
        Route::get('/andon', AndonInfoController::class)->name('andon');
    });
});
