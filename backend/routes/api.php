<?php

use App\Http\Controllers\RoutePlannerController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/users', [UserController::class, 'index']);
Route::post('/users', [UserController::class, 'store']);

Route::get('/nearest-stop', [RoutePlannerController::class, 'nearestStop']);
Route::get('/plan-route', [RoutePlannerController::class, 'planRoute']);
Route::get('/routes/list', [RoutePlannerController::class, 'listRoutes']);
Route::get('/trip-details/{trip_id}', [RoutePlannerController::class, 'tripDetails'])->whereNumber('trip_id');

Route::get('/stops', [ScheduleController::class, 'stops']);
Route::get('/schedules/routes/{route_id}/stops/{stop_id}/departures', [ScheduleController::class, 'routeStopDepartures'])
    ->whereNumber('route_id')
    ->whereNumber('stop_id');
Route::get('/schedules/routes/{route_id}/pattern', [ScheduleController::class, 'routePattern'])->whereNumber('route_id');
Route::get('/schedules/stops/{stop_id}/departures', [ScheduleController::class, 'stopDepartures'])->whereNumber('stop_id');
