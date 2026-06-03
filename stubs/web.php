<?php

use App\Events\RaceRegistered;
use App\Jobs\SendWelcomeEmail;
use App\Models\Race;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

/**
 * 手動計装ヘルパー: 任意の処理を 1 つのスパンで包む
 */
function traced(string $tracerName, string $spanName, callable $fn, array $attributes = [], int $kind = SpanKind::KIND_INTERNAL): mixed
{
    $tracer = Globals::tracerProvider()->getTracer($tracerName);
    $span = $tracer->spanBuilder($spanName)->setSpanKind($kind);
    foreach ($attributes as $k => $v) {
        $span->setAttribute($k, $v);
    }
    $span = $span->startSpan();
    $scope = $span->activate();
    try {
        $result = $fn($span);
        $span->setStatus(StatusCode::STATUS_OK);
        return $result;
    } catch (\Throwable $e) {
        $span->recordException($e);
        $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
        throw $e;
    } finally {
        $scope->detach();
        $span->end();
    }
}

// ===== HTTP / Eloquent (auto-instrumentation だけで動く) =====
Route::get('/', function () {
    Log::info('home accessed');
    return response()->json([
        'app' => 'Laravel OTel Lab',
        'time' => now()->toIso8601String(),
    ]);
});

Route::get('/races', function () {
    $races = Race::orderBy('id')->limit(20)->get();
    Log::info('races listed', ['count' => $races->count()]);
    return response()->json($races);
});

Route::get('/races/{id}', function (int $id) {
    $race = Race::findOrFail($id);
    return response()->json($race);
});

// ===== HTTP Client (auto-instrumentation で動く) =====
Route::get('/api-fanout', function () {
    $responses = Http::pool(fn (Pool $p) => [
        $p->as('a')->get('https://jsonplaceholder.typicode.com/posts/1'),
        $p->as('b')->get('https://jsonplaceholder.typicode.com/posts/2'),
        $p->as('c')->get('https://jsonplaceholder.typicode.com/posts/3'),
    ]);
    return response()->json([
        'a' => $responses['a']->json('title'),
        'b' => $responses['b']->json('title'),
        'c' => $responses['c']->json('title'),
    ]);
});

Route::get('/boom', function () {
    Log::warning('about to throw');
    throw new \RuntimeException('intentional boom for tracing');
});

Route::get('/slow', function () {
    usleep(150_000);
    $races = Race::all();
    foreach ($races as $r) {
        Race::find($r->id);
    }
    return response()->json(['ok' => true, 'count' => $races->count()]);
});

// ===== Cache (手動計装) =====
// auto-laravel に Cache hook が無いので手動でスパン化
Route::get('/cache-demo', function () {
    $key = 'cache-demo:hot-data';

    return traced('app.cache', 'Cache::remember', function ($span) use ($key) {
        $wasHit = Cache::has($key);
        $span->setAttribute('cache.key', $key);
        $span->setAttribute('cache.hit', $wasHit);

        $value = Cache::remember($key, 30, function () {
            usleep(100_000);
            return ['computed_at' => now()->toIso8601String()];
        });

        return response()->json(['cached' => $value, 'was_hit' => $wasHit]);
    }, ['cache.store' => config('cache.default')]);
});

// ===== Event + Listener (手動計装) =====
// auto-laravel に Event hook が無いので手動で
Route::get('/event-demo', function () {
    return traced('app.events', 'Event:RaceRegistered.dispatch', function ($span) {
        $event = new RaceRegistered(
            raceName: 'Sample Marathon X',
            participantCount: rand(100, 5000),
        );
        $span->setAttribute('event.name', RaceRegistered::class);
        $span->setAttribute('event.race_name', $event->raceName);
        $span->setAttribute('event.participant_count', $event->participantCount);

        Event::dispatch($event);

        return response()->json(['ok' => 'event dispatched']);
    });
});

// ===== Queue Job (手動計装) =====
Route::get('/job-demo', function () {
    return traced('app.jobs', 'Job:SendWelcomeEmail.dispatch', function ($span) {
        $email = 'demo@example.com';
        $span->setAttribute('job.name', SendWelcomeEmail::class);
        $span->setAttribute('job.queue.driver', config('queue.default'));
        $span->setAttribute('job.payload.email', $email);

        SendWelcomeEmail::dispatch($email);

        return response()->json(['ok' => 'job dispatched']);
    });
});

// ===== Artisan (auto-instrumentation で動く) =====
Route::get('/artisan-demo', function () {
    Artisan::call('cache:clear');
    return response()->json([
        'ok' => 'artisan ran',
        'output' => Artisan::output(),
    ]);
});

// ===== 複合デモ: 1 リクエストで全カテゴリの span が出るやつ =====
Route::get('/full-demo', function () {
    return traced('app.demo', 'full-demo', function () {
        // Eloquent
        Race::take(2)->get();
        // Cache
        traced('app.cache', 'Cache::remember(full)', fn () => Cache::remember('full-demo', 5, fn () => 'x'));
        // Event
        traced('app.events', 'Event:RaceRegistered.dispatch(full)', fn () => Event::dispatch(new RaceRegistered('Full', 1)));
        // Job
        traced('app.jobs', 'Job:SendWelcomeEmail.dispatch(full)', fn () => SendWelcomeEmail::dispatch('full@example.com'));
        // HTTP Client
        Http::get('https://jsonplaceholder.typicode.com/posts/4');
        return response()->json(['ok' => 'all categories spanned']);
    });
});
