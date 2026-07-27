<?php

use App\Http\Controllers\GoogleOAuthCallbackController;
use App\Http\Controllers\GoogleOAuthRedirectController;
use App\Http\Controllers\OpportunitiesExportController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->group(function () {
    Route::get('/admin/opportunities/export', OpportunitiesExportController::class)
        ->name('opportunities.export');

    Route::get('/oauth/google/redirect', GoogleOAuthRedirectController::class)
        ->name('oauth.google.redirect');
    Route::get('/oauth/google/callback', GoogleOAuthCallbackController::class)
        ->name('oauth.google.callback');
});
