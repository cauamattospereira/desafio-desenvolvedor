<?php

use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

/**
 * File upload route
 * Support CSV/XLSX files
 * 
 * The CSV file need to be separated by ';', other separator logic is not implemented.
 * 
 * The uploaded file are breaked in chunks and root document.
 * The root document have the filename of the original file uploaded.
 * The chunks documents have the filename composed by YYYY-MM-DD_{$chunkIndex}_$documentOriginalName
 * 
 * Chunks documents are limited to 10000 lines per document. This limit is needed to not surpass the
 * max limit size for documents on MongoDB (16mb per document) 
 * 
 * $chunkIndex variable represents the number of the refereed chunk.
 */
Route::post('/files', [FileController::class, 'upload']);

/**
 * File Upload History.
 * 
 * The user is able to search through the history of uploaded files.
 * Params:
 *  Filename: The entire filename
 *  Reference Date:
 *      Brazilian: YYYY-MM-DD -> return the query based on upload_date_brasilia_local_time field
 *      UTC: YYYY-MM-DD -> return the query based on created_at field
 */
Route::get('/files', [FileController::class, 'history']);

Route::get('/files/search', [FileController::class, 'search']);