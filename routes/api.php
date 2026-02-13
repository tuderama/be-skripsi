<?php

use App\Http\Controllers\AirDataController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/register', [AuthController::class, 'register']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::post('/user/profil-image', [UserController::class, 'updateImageProfile'])->middleware('auth:sanctum');
Route::put('/user/profil', [UserController::class, 'updateProfile'])->middleware('auth:sanctum');
Route::get('/user/profil', [UserController::class, 'getUserLogin'])->middleware('auth:sanctum');
Route::get('/air/co', [AirDataController::class, 'getCOData'])->middleware('auth:sanctum');
Route::get('/air/pm25', [AirDataController::class, 'getPM25Data'])->middleware('auth:sanctum');
Route::get('/air/device', [AirDataController::class, 'getDevices'])->middleware('auth:sanctum');
Route::post('/update-token', [AuthController::class, 'updateFcmToken'])->middleware('auth:sanctum');
Route::get('/notifications/{user_id}', [NotificationController::class, 'getUserNotifications'])->middleware('auth:sanctum');
Route::delete('/notifications/delete-all/{user_id}', [NotificationController::class, 'deleteAllNotifications'])->middleware('auth:sanctum');
