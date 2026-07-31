<?php

namespace Tests\Feature;

use App\Support\SentryEventFilter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Sentry\Event;
use Sentry\EventHint;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Testează App\Support\SentryEventFilter::beforeSend() — logica de filtrare
 * folosită de config/sentry.php pentru a decide ce evenimente ajung efectiv
 * trimise către Sentry.
 */
class SentryEventFilterTest extends TestCase
{
    private function hintFor(\Throwable $exception): EventHint
    {
        $hint = new EventHint;
        $hint->exception = $exception;

        return $hint;
    }

    public function test_drops_everything_in_the_local_environment(): void
    {
        app()['env'] = 'local';

        $event = Event::createEvent();

        $this->assertNull(SentryEventFilter::beforeSend($event, $this->hintFor(new RuntimeException)));
    }

    public function test_drops_not_found_exceptions(): void
    {
        app()['env'] = 'production';

        $event = Event::createEvent();

        $this->assertNull(
            SentryEventFilter::beforeSend($event, $this->hintFor(new NotFoundHttpException))
        );
    }

    public function test_drops_model_not_found_exceptions(): void
    {
        app()['env'] = 'production';

        $event = Event::createEvent();

        $this->assertNull(
            SentryEventFilter::beforeSend($event, $this->hintFor(new ModelNotFoundException))
        );
    }

    public function test_drops_validation_exceptions(): void
    {
        app()['env'] = 'production';

        $event = Event::createEvent();
        $exception = ValidationException::withMessages(['email' => 'invalid']);

        $this->assertNull(SentryEventFilter::beforeSend($event, $this->hintFor($exception)));
    }

    public function test_keeps_other_exceptions_in_production(): void
    {
        app()['env'] = 'production';

        $event = Event::createEvent();

        $this->assertSame(
            $event,
            SentryEventFilter::beforeSend($event, $this->hintFor(new RuntimeException))
        );
    }

    public function test_keeps_events_without_an_exception_hint_in_production(): void
    {
        app()['env'] = 'production';

        $event = Event::createEvent();

        $this->assertSame($event, SentryEventFilter::beforeSend($event, null));
    }
}
