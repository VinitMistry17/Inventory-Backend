<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\ProfileController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

    Route::get('/items', [ItemController::class, 'index']);
    Route::get('/items/{id}', [ItemController::class, 'show']);
    Route::post('/items', [ItemController::class, 'store']);
    Route::put('/items/{id}', [ItemController::class, 'update']);
    Route::delete('/items/{id}', [ItemController::class, 'destroy']);
    Route::post('/items/{id}/photos', [ItemController::class, 'uploadPhotos']);
    Route::delete('/items/{id}/photos/{photoId}', [ItemController::class, 'deletePhoto']);
    Route::post('/items/{id}/document', [ItemController::class, 'uploadDocument']);
    Route::delete('/items/{id}/document', [ItemController::class, 'deleteDocument']);

    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::get('/alerts/count', [AlertController::class, 'count']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);

    Route::put('/settings/reminder-timing', function (Request $request) {
        $data = $request->validate([
            'days_before' => ['required', 'integer', 'min:1', 'max:60'],
        ]);

        $request->user()->update([
            'reminder_days_before' => $data['days_before'],
        ]);

        return response()->json([
            'message' => 'Reminder timing updated successfully',
            'reminder_days_before' => $request->user()->fresh()->reminder_days_before,
        ], 200);
    });

    Route::get('/user', function (Request $request) {
        return $request->user();
    });
});
