<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/dashboard', [DashboardController::class, 'index']);
Route::patch('/sessions/{guid}', [DashboardController::class, 'updateSession']);
Route::post('/orphans/dismiss', [DashboardController::class, 'dismissOrphanGroup']);
