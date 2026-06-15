<?php

use App\Http\Controllers\DispatcherController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/telemetry', [DispatcherController::class, 'telemetry']);

Route::post('/dispatch', [DispatcherController::class, 'dispatch']);

Route::post('/strategy', [DispatcherController::class, 'setStrategy']);

Route::post('/nodes/toggle', [DispatcherController::class, 'toggleNode']);

Route::post('/chaos/peak-load', [DispatcherController::class, 'chaosPeakLoad']);
Route::post('/chaos/reset',     [DispatcherController::class, 'chaosReset']);
