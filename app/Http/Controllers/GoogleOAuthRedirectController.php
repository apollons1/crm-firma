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
            'scope' => config('services.google.scopes'),
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        session(['google_oauth_state' => $provider->getState()]);

        return redirect()->away($authorizationUrl);
    }
}
