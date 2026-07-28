<?php

use App\Http\Controllers\EmailAttachmentDownloadController;
use App\Http\Controllers\GoogleOAuthCallbackController;
use App\Http\Controllers\GoogleOAuthRedirectController;
use App\Http\Controllers\OpportunitiesExportController;
use App\Http\Controllers\TwilioWhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Webhook Twilio — neautentificat (Twilio nu are sesiune CRM), autenticitatea
// se verifică prin semnătura X-Twilio-Signature, nu prin login/CSRF.
Route::post('/webhooks/twilio/whatsapp', TwilioWhatsAppWebhookController::class)
    ->name('webhooks.twilio.whatsapp');

Route::middleware('auth')->group(function () {
    Route::get('/admin/opportunities/export', OpportunitiesExportController::class)
        ->name('opportunities.export');

    Route::get('/admin/email-attachments/{emailAttachment}/download', EmailAttachmentDownloadController::class)
        ->name('email-attachments.download');

    Route::get('/oauth/google/redirect', GoogleOAuthRedirectController::class)
        ->name('oauth.google.redirect');
    Route::get('/oauth/google/callback', GoogleOAuthCallbackController::class)
        ->name('oauth.google.callback');
});
