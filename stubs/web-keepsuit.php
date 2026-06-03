<?php

/**
 * keepsuit/laravel-opentelemetry 版 (手動計装ナシ)
 * 同じエンドポイントを叩いて、auto-instrumentation だけで Cache/Event/Job が span 化されるか確認する用
 */

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

Route::get('/', function () {
    Log::info('home accessed (keepsuit)');
    return response()->json(['app' => 'Laravel OTel Lab (keepsuit)', 'time' => now()->toIso8601String()]);
});

Route::get('/races', function () {
    $races = Race::orderBy('id')->limit(20)->get();
    return response()->json($races);
});

Route::get('/races/{id}', function (int $id) {
    return response()->json(Race::findOrFail($id));
});

Route::get('/api-fanout', function () {
    $responses = Http::pool(fn (Pool $p) => [
        $p->as('a')->get('https://jsonplaceholder.typicode.com/posts/1'),
        $p->as('b')->get('https://jsonplaceholder.typicode.com/posts/2'),
        $p->as('c')->get('https://jsonplaceholder.typicode.com/posts/3'),
    ]);
    return response()->json(['a' => $responses['a']->json('title'), 'b' => $responses['b']->json('title'), 'c' => $responses['c']->json('title')]);
});

Route::get('/boom', function () { throw new \RuntimeException('intentional boom'); });

Route::get('/slow', function () {
    usleep(150_000);
    $races = Race::all();
    foreach ($races as $r) { Race::find($r->id); }
    return response()->json(['ok' => true, 'count' => $races->count()]);
});

// ===== ここが本命: 手動計装ヘルパー無し =====

Route::get('/cache-demo', function () {
    // 手動計装一切なし。keepsuit が Cache::remember を hook して span 化するはず
    $value = Cache::remember('cache-demo:hot-data', 30, function () {
        usleep(100_000);
        return ['computed_at' => now()->toIso8601String()];
    });
    return response()->json(['cached' => $value]);
});

Route::get('/cache-miss', function () {
    $key = 'cache-demo:miss:' . uniqid();
    Cache::put($key, ['stamp' => now()->toIso8601String()], 5);
    return response()->json(['put_key' => $key]);
});

Route::get('/event-demo', function () {
    // 手動計装一切なし。keepsuit が Event::dispatch を hook するはず
    Event::dispatch(new RaceRegistered(raceName: 'Sample X', participantCount: rand(100, 5000)));
    return response()->json(['ok' => 'event dispatched']);
});

Route::get('/job-demo', function () {
    // 手動計装一切なし。keepsuit が Queue::dispatch を hook するはず
    SendWelcomeEmail::dispatch('demo@example.com');
    return response()->json(['ok' => 'job dispatched']);
});

Route::get('/artisan-demo', function () {
    Artisan::call('cache:clear');
    return response()->json(['ok' => 'artisan ran', 'output' => Artisan::output()]);
});

Route::get('/full-demo', function () {
    Race::take(2)->get();
    Cache::remember('full-demo', 5, fn () => 'x');
    Event::dispatch(new RaceRegistered('Full', 1));
    SendWelcomeEmail::dispatch('full@example.com');
    Http::get('https://jsonplaceholder.typicode.com/posts/4');
    return response()->json(['ok' => 'all categories spanned']);
});
