<?php

namespace App\Support;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Filtrează ce ajunge efectiv în Sentry (config/sentry.php "before_send"):
 * nimic în local (APP_ENV=local), și nici 404-uri / erori de validare —
 * acelea sunt UX normal, nu erori reale de urmărit. Referențiată ca
 * [self::class, 'beforeSend'] (callable serializabil), nu ca Closure —
 * config/sentry.php trebuie să rămână compatibil cu `artisan config:cache`.
 */
class SentryEventFilter
{
    public static function beforeSend(Event $event, ?EventHint $hint): ?Event
    {
        if (app()->environment('local')) {
            return null;
        }

        $exception = $hint?->exception;

        if ($exception instanceof NotFoundHttpException || $exception instanceof ModelNotFoundException) {
            return null;
        }

        if ($exception instanceof ValidationException) {
            return null;
        }

        return $event;
    }
}
