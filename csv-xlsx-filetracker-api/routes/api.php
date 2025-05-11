<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/files/upload', [FileController::class, 'upload']);

/**
 * File Upload History.
 * 
 * The user is able to search through the history of uploaded files.
 * Optional: Search by filename or reference date (upload date).
 */
Route::get('/files/history', [FileController::class, 'history']);
