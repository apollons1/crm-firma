<?php

namespace App\Http\Controllers;

use App\Models\GoogleToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use League\OAuth2\Client\Provider\Google;

class GoogleOAuthCallbackController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string'],
            'state' => ['required', 'string'],
        ]);

        abort_unless(
            hash_equals(session('google_oauth_state', ''), $request->string('state')->toString()),
            403,
            'Stare OAuth invalidă.'
        );

        session()->forget('google_oauth_state');

        $provider = new Google([
            'clientId' => config('services.google.client_id'),
            'clientSecret' => config('services.google.client_secret'),
            'redirectUri' => config('services.google.redirect_uri'),
        ]);

        $accessToken = $provider->getAccessToken('authorization_code', [
            'code' => $request->string('code')->toString(),
        ]);

        $googleUser = $provider->getResourceOwner($accessToken);

        $token = GoogleToken::first() ?? new GoogleToken;

        $token->fill([
            'email' => $googleUser->getEmail(),
            'access_token' => $accessToken->getToken(),
            'refresh_token' => $accessToken->getRefreshToken() ?? $token->refresh_token,
            'expires_at' => now()->setTimestamp($accessToken->getExpires()),
            'scopes' => [
                'https://www.googleapis.com/auth/gmail.send',
                'https://www.googleapis.com/auth/gmail.readonly',
                'https://www.googleapis.com/auth/userinfo.email',
            ],
        ])->save();

        return redirect('/admin')->with('success', 'Cont Gmail conectat: '.$token->email);
    }
}
