<?php

use App\Models\Module;
use App\Models\Video;
use App\Services\YouTubeImportService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['services.youtube.api_key' => 'yt-key']);
    $this->module = Module::factory()->create();
});

function youtubePage(int $from, int $to, ?string $nextToken = null): array
{
    $items = collect(range($from, $to))->map(fn ($i) => ['snippet' => [
        'title' => "Clase {$i}",
        'description' => "Descripción {$i}",
        'resourceId' => ['videoId' => "vid{$i}"],
        'thumbnails' => ['high' => ['url' => "https://img.example/{$i}.jpg"]],
    ]])->all();

    return array_filter(['items' => $items, 'nextPageToken' => $nextToken]);
}

test('every page of a long playlist is imported in order', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::sequence()
        ->push(youtubePage(1, 50, 'PAGE2'))
        ->push(youtubePage(51, 100, 'PAGE3'))
        ->push(youtubePage(101, 120)),
    ]);

    $count = app(YouTubeImportService::class)->importPlaylist('PL123', $this->module);

    expect($count)->toBe(120)
        ->and(Video::count())->toBe(120)
        ->and(Video::orderBy('order_index')->first()->video_id)->toBe('vid1')
        ->and(Video::orderByDesc('order_index')->first()->video_id)->toBe('vid120')
        ->and(Video::orderByDesc('order_index')->first()->order_index)->toBe(119);

    Http::assertSentCount(3);
    Http::assertSent(fn ($request) => ($request['pageToken'] ?? null) === 'PAGE2');
    Http::assertSent(fn ($request) => ($request['pageToken'] ?? null) === 'PAGE3');
    Http::assertSent(fn ($request) => ! isset($request['pageToken']) && $request['maxResults'] === 50);
});

test('a single page playlist makes a single request', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::response(youtubePage(1, 3))]);

    expect(app(YouTubeImportService::class)->importPlaylist('PL123', $this->module))->toBe(3);

    Http::assertSentCount(1);
});

test('private and deleted entries are skipped and the order stays continuous', function () {
    $page = youtubePage(1, 3);
    $page['items'][1] = ['snippet' => ['title' => 'Private video', 'description' => '', 'resourceId' => ['videoId' => 'hidden']]];
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::response($page)]);

    $count = app(YouTubeImportService::class)->importPlaylist('PL123', $this->module);

    expect($count)->toBe(2)
        ->and(Video::orderBy('order_index')->pluck('video_id')->all())->toBe(['vid1', 'vid3'])
        ->and(Video::orderBy('order_index')->pluck('order_index')->all())->toBe([0, 1]);
});

test('a playlist with only unavailable entries is reported as empty', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::response(['items' => [
        ['snippet' => ['title' => 'Deleted video', 'resourceId' => ['videoId' => 'gone']]],
    ]])]);

    expect(fn () => app(YouTubeImportService::class)->importPlaylist('PL123', $this->module))->toThrow(Exception::class);
    expect(Video::count())->toBe(0);
});

test('nothing is saved when a later page fails', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::sequence()
        ->push(youtubePage(1, 50, 'PAGE2'))
        ->push([], 500),
    ]);

    expect(fn () => app(YouTubeImportService::class)->importPlaylist('PL123', $this->module))
        ->toThrow(Exception::class, 'No se pudo conectar con la API de YouTube');
    expect(Video::count())->toBe(0);
});

test('a playlist that never ends is stopped', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::response(youtubePage(1, 2, 'FOREVER'))]);

    expect(fn () => app(YouTubeImportService::class)->importPlaylist('PL123', $this->module))
        ->toThrow(Exception::class, 'demasiado larga');
    expect(Video::count())->toBe(0);
    Http::assertSentCount(200);
});

test('importing again updates the videos instead of duplicating them', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::sequence()
        ->push(youtubePage(1, 50, 'PAGE2'))->push(youtubePage(51, 60))
        ->push(youtubePage(1, 50, 'PAGE2'))->push(youtubePage(51, 60)),
    ]);

    app(YouTubeImportService::class)->importPlaylist('PL123', $this->module);
    app(YouTubeImportService::class)->importPlaylist('PL123', $this->module);

    expect(Video::count())->toBe(60);
});

test('the console command imports the whole playlist', function () {
    Http::fake(['googleapis.com/youtube/v3/playlistItems*' => Http::sequence()
        ->push(youtubePage(1, 50, 'PAGE2'))
        ->push(youtubePage(51, 75)),
    ]);

    $this->artisan('youtube:import', ['playlistId' => 'PL123', 'moduleId' => $this->module->id])
        ->expectsOutputToContain('Successfully imported 75 videos')
        ->assertSuccessful();
});
