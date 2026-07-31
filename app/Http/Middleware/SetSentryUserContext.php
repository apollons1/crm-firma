<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

/**
 * Atașează userul autentificat la fiecare eveniment trimis către Sentry
 * (id, email, rolurile Spatie) — util pentru triaj, fără date suplimentare
 * sensibile (nume, telefon, parolă).
 */
class SetSentryUserContext
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            configureScope(function (Scope $scope) use ($user): void {
                $scope->setUser([
                    'id' => $user->id,
                    'email' => $user->email,
                    'roles' => $user->roles->pluck('name')->toArray(),
                ]);
            });
        }

        return $next($request);
    }
}
