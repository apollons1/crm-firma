<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Impune 2FA obligatoriu pentru super_admin/admin, evaluat corect la
 * fiecare cerere autentificată (spre deosebire de parametrul isRequired al
 * multiFactorAuthentication() din AdminPanelProvider, care e evaluat o
 * singură dată la boot-ul panoului — înainte ca userul să fie autentificat
 * — deci un closure bazat pe rol acolo evaluează mereu false și nu impune
 * nimic niciodată; vezi App\Filament\Pages\Auth\SetUpRequiredMultiFactor).
 */
class EnsureRequiredMultiFactorAuthenticationIsEnabled
{
    private const REQUIRED_ROLES = ['super_admin', 'admin'];

    private const ALLOWED_ROUTE_NAMES = [
        'filament.admin.pages.configurare-2fa-obligatorie',
        'filament.admin.auth.logout',
        // Un user cu AMBELE parola expirată ȘI 2FA neconfigurat trebuie să
        // poată termina fluxul de schimbare a parolei fără să fie
        // redirecționat concurent spre configurarea 2FA — EnsurePasswordIsNotExpired
        // rulează primul și oricum îl ține pe pagina de parolă până o schimbă.
        'filament.admin.pages.parola-expirata',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $user->hasAnyRole(self::REQUIRED_ROLES)
            && blank($user->app_authentication_secret)
            && (! $request->routeIs(...self::ALLOWED_ROUTE_NAMES))
        ) {
            return redirect()->route('filament.admin.pages.configurare-2fa-obligatorie');
        }

        return $next($request);
    }
}
