<?php

use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SourceController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/sources', [SourceController::class, 'index']);
Route::get('/search', SearchController::class);
