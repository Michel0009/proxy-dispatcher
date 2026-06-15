<?php

use App\Http\Controllers\DispatcherController;
use Illuminate\Support\Facades\Route;

Route::get('/', [DispatcherController::class, 'dashboard']);

