<?php

use App\Http\Controllers\SendBulkNotificationsController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/notifications/bulk', SendBulkNotificationsController::class);
