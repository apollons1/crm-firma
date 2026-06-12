<?php

use App\Http\Controllers\OpportunitiesExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/admin/opportunities/export', OpportunitiesExportController::class)
        ->name('opportunities.export');
});
