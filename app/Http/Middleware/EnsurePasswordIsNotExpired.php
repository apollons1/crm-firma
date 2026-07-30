<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Parola expiră la 90 de zile pentru super_admin/admin. Dacă a expirat (sau
 * nu are deloc password_changed_at, ex: cont creat înainte de politică),
 * userul e redirecționat forțat spre pagina de schimbare a parolei, la
 * fiecare cerere autentificată din panou — până își schimbă parola.
 */
class EnsurePasswordIsNotExpired
{
    private const EXPIRY_DAYS = 90;

    private const ALLOWED_ROUTE_NAMES = [
        'filament.admin.pages.parola-expirata',
        'filament.admin.auth.logout',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user instanceof User
            && $this->isExpired($user)
            && (! $request->routeIs(...self::ALLOWED_ROUTE_NAMES))
        ) {
            return redirect()->route('filament.admin.pages.parola-expirata');
        }

        return $next($request);
    }

    private function isExpired(User $user): bool
    {
        if (! $user->hasAnyRole(['super_admin', 'admin'])) {
            return false;
        }

        return $user->password_changed_at === null
            || $user->password_changed_at->lt(now()->subDays(self::EXPIRY_DAYS));
    }
}
