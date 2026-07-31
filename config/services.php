<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'scopes' => [
            'https://www.googleapis.com/auth/gmail.send',
            'https://www.googleapis.com/auth/gmail.readonly',
            'https://www.googleapis.com/auth/gmail.modify',
            'https://www.googleapis.com/auth/userinfo.email',
        ],
    ],

    // Twilio (integrare WhatsApp) — vezi App\Services\WhatsAppService.
    // Template-urile aprobate se țin în DB (whatsapp_templates), nu aici.
    'twilio' => [
        'sid' => env('TWILIO_ACCOUNT_SID'),
        'token' => env('TWILIO_AUTH_TOKEN'),
        'whatsapp_from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    // Stripe (linkuri de plată din oportunități) — vezi App\Services\StripeService.
    // ATENȚIE: acest cont Stripe e PARTAJAT cu Selgora, o altă platformă care
    // procesează plăți separat pe el. Webhook-ul (StripeWebhookController)
    // procesează DOAR evenimentele cu metadata.opportunity_id — orice altceva
    // (inclusiv tot ce vine de la Selgora) e ignorat complet.
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'publishable_key' => env('STRIPE_PUBLISHABLE_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    // UptimeRobot (webhook de downtime/recovery) — vezi
    // App\Http\Controllers\UptimeRobotWebhookController. Token-ul e verificat
    // din URL (/webhooks/uptimerobot/{token}), nu printr-o semnătură — nu
    // avem alt secret comun cu UptimeRobot.
    'uptimerobot' => [
        'webhook_token' => env('UPTIMEROBOT_WEBHOOK_TOKEN'),
    ],

];
