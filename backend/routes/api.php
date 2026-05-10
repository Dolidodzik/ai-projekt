<?php

use App\Http\Controllers\RoutePlannerController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);

Route::get('/nearest-stop', [RoutePlannerController::class, 'nearestStop']);
Route::get('/plan-route', [RoutePlannerController::class, 'planRoute']);
