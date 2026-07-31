<?php

use App\Http\Controllers\EmailAttachmentDownloadController;
use App\Http\Controllers\GoogleOAuthCallbackController;
use App\Http\Controllers\GoogleOAuthRedirectController;
use App\Http\Controllers\OpportunitiesExportController;
use App\Http\Controllers\StripeWebhookController;
use App\Http\Controllers\TwilioWhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

// Webhook Twilio — neautentificat (Twilio nu are sesiune CRM), autenticitatea
// se verifică prin semnătura X-Twilio-Signature, nu prin login/CSRF.
// Rate limit: 100 req/min/IP (limiter "webhooks", App\Providers\AppServiceProvider).
Route::post('/webhooks/twilio/whatsapp', TwilioWhatsAppWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.twilio.whatsapp');

// Webhook Stripe — neautentificat, autenticitatea se verifică prin
// semnătura Stripe-Signature, nu prin login/CSRF. Procesează DOAR
// evenimentele cu metadata.opportunity_id (cont partajat cu Selgora).
// Rate limit: 100 req/min/IP (limiter "webhooks", App\Providers\AppServiceProvider).
Route::post('/webhooks/stripe', StripeWebhookController::class)
    ->middleware('throttle:webhooks')
    ->name('webhooks.stripe');

// Pagini publice de retur după Checkout Session Stripe — clientul nu are
// sesiune CRM, deci nu pot fi în panoul admin. Rate limit: 60 req/min/IP.
Route::middleware('throttle:60,1')->group(function () {
    Route::view('/plati/succes', 'payments.success')->name('payments.success');
    Route::view('/plati/anulat', 'payments.cancel')->name('payments.cancel');
});

Route::middleware('auth')->group(function () {
    Route::get('/admin/opportunities/export', OpportunitiesExportController::class)
        ->name('opportunities.export');

    Route::get('/admin/email-attachments/{emailAttachment}/download', EmailAttachmentDownloadController::class)
        ->name('email-attachments.download');

    Route::get('/oauth/google/redirect', GoogleOAuthRedirectController::class)
        ->name('oauth.google.redirect');
    Route::get('/oauth/google/callback', GoogleOAuthCallbackController::class)
        ->name('oauth.google.callback');

    // TEMPORAR — doar pentru verificarea integrării Sentry (vizitează o dată,
    // confirmă că eroarea apare în dashboard-ul Sentry, apoi ȘTERGE ruta asta).
    Route::get('/test-sentry', function () {
        throw new RuntimeException('Test Sentry: eroare generată manual pentru verificare.');
    })->name('test-sentry');
});
