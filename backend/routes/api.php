<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RideHistoryController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/auth/me', [ProfileController::class, 'me']);
    Route::patch('/auth/profile', [ProfileController::class, 'update']);
    Route::patch('/auth/password', [ProfileController::class, 'updatePassword']);

    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);

    Route::get('/ticket-types', [TicketController::class, 'types']);
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets/purchase', [TicketController::class, 'purchase']);
    Route::post('/tickets/{id}/activate', [TicketController::class, 'activate']);

    Route::get('/ride-history', [RideHistoryController::class, 'index']);
    Route::post('/ride-history/add', [RideHistoryController::class, 'store']);
});
