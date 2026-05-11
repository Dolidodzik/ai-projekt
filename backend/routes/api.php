<?php

use App\Http\Controllers\RoutePlannerController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);

Route::get('/nearest-stop', [RoutePlannerController::class, 'nearestStop']);
Route::get('/plan-route', [RoutePlannerController::class, 'planRoute']);
Route::get('/routes/list', [RoutePlannerController::class, 'listRoutes']);
Route::get('/trip-details/{trip_id}', [RoutePlannerController::class, 'tripDetails'])->whereNumber('trip_id');
