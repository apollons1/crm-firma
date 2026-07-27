<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use League\OAuth2\Client\Provider\Google;

class GoogleOAuthRedirectController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $provider = new Google([
            'clientId' => config('services.google.client_id'),
            'clientSecret' => config('services.google.client_secret'),
            'redirectUri' => config('services.google.redirect_uri'),
        ]);

        $authorizationUrl = $provider->getAuthorizationUrl([
            'scope' => [
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/userinfo.email',
            ],
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        session(['google_oauth_state' => $provider->getState()]);

        return redirect()->away($authorizationUrl);
    }
}
