<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Video;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class YouTubeImportService
{
    /** Videos per request: the maximum the YouTube API allows. */
    private const PAGE_SIZE = 50;

    /** Safety limit on the number of pages followed (10,000 videos). */
    private const MAX_PAGES = 200;

    /**
     * Import every video in a YouTube playlist into the given module, following all the pages of the playlist.
     * Private and deleted entries (YouTube returns them without a thumbnail) are skipped.
     * Safe to re-run: existing videos (matched by video_id) are updated, not duplicated.
     * Nothing is saved if any page fails to load.
     *
     * @return int How many videos were imported
     */
    public function importPlaylist(string $playlistIdOrUrl, Module $module): int
    {
        $items = collect($this->fetchPlaylistItems($this->extractPlaylistId($playlistIdOrUrl)))
            ->filter(fn (array $item) => ! empty($item['snippet']['resourceId']['videoId']) && ! empty($item['snippet']['thumbnails']))
            ->values();

        if ($items->isEmpty()) {
            throw new \Exception('No se encontraron videos en esta playlist, o el ID/URL es incorrecto.');
        }

        DB::transaction(function () use ($items, $module) {
            foreach ($items as $position => $item) {
                $snippet = $item['snippet'];

                Video::updateOrCreate(
                    ['video_id' => $snippet['resourceId']['videoId']],
                    [
                        'module_id' => $module->id,
                        'title' => $snippet['title'],
                        'description' => $snippet['description'] ?? '',
                        'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'],
                        'order_index' => $position,
                    ]
                );
            }
        });

        return $items->count();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchPlaylistItems(string $playlistId): array
    {
        $items = [];
        $pageToken = null;
        $pages = 0;

        do {
            $response = Http::get('https://www.googleapis.com/youtube/v3/playlistItems', array_filter([
                'part' => 'snippet',
                'maxResults' => self::PAGE_SIZE,
                'playlistId' => $playlistId,
                'pageToken' => $pageToken,
                'key' => config('services.youtube.api_key'),
            ]));

            if ($response->failed()) {
                throw new \Exception('No se pudo conectar con la API de YouTube. Revisá el YOUTUBE_API_KEY.');
            }

            array_push($items, ...$response->json('items', []));

            $pageToken = $response->json('nextPageToken');
            $pages++;

            if ($pageToken && $pages >= self::MAX_PAGES) {
                throw new \Exception('La playlist es demasiado larga para importarla de una vez.');
            }
        } while ($pageToken);

        return $items;
    }

    /**
     * Import a single YouTube video into the given module, appended to the end.
     */
    public function importVideo(string $videoIdOrUrl, Module $module): Video
    {
        $videoId = $this->extractVideoId($videoIdOrUrl);

        $response = Http::get('https://www.googleapis.com/youtube/v3/videos', [
            'part' => 'snippet',
            'id' => $videoId,
            'key' => config('services.youtube.api_key'),
        ]);

        if ($response->failed()) {
            throw new \Exception('No se pudo conectar con la API de YouTube. Revisá el YOUTUBE_API_KEY.');
        }

        $item = $response->json('items.0');

        if (! $item) {
            throw new \Exception('No se encontró ningún video con ese ID/URL.');
        }

        $snippet = $item['snippet'];

        return Video::updateOrCreate(
            ['video_id' => $videoId],
            [
                'module_id' => $module->id,
                'title' => $snippet['title'],
                'description' => $snippet['description'],
                'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'],
                'order_index' => ((int) $module->videos()->max('order_index')) + 1,
            ]
        );
    }

    /**
     * Accept either a raw playlist ID or a full YouTube playlist URL.
     */
    public function extractPlaylistId(string $input): string
    {
        $input = trim($input);

        if (! str_contains($input, '://')) {
            return $input;
        }

        parse_str((string) parse_url($input, PHP_URL_QUERY), $query);

        return $query['list'] ?? $input;
    }

    /**
     * Accept either a raw video ID or a full YouTube watch/share URL.
     */
    public function extractVideoId(string $input): string
    {
        $input = trim($input);

        if (! str_contains($input, '://')) {
            return $input;
        }

        $host = (string) parse_url($input, PHP_URL_HOST);

        if (str_contains($host, 'youtu.be')) {
            return ltrim((string) parse_url($input, PHP_URL_PATH), '/');
        }

        parse_str((string) parse_url($input, PHP_URL_QUERY), $query);

        return $query['v'] ?? $input;
    }
}
