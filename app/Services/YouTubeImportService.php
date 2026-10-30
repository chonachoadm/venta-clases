<?php

namespace App\Services;

use App\Models\Module;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class YouTubeImportService
{
    /**
     * Import every video in a YouTube playlist into the given module.
     * Safe to re-run: existing videos (matched by video_id) are updated, not duplicated.
     */
    public function importPlaylist(string $playlistIdOrUrl, Module $module): int
    {
        $response = Http::get('https://www.googleapis.com/youtube/v3/playlistItems', [
            'part' => 'snippet',
            'maxResults' => 50,
            'playlistId' => $this->extractPlaylistId($playlistIdOrUrl),
            'key' => config('services.youtube.api_key'),
        ]);

        if ($response->failed()) {
            throw new \Exception('No se pudo conectar con la API de YouTube. Revisá el YOUTUBE_API_KEY.');
        }

        $items = $response->json('items', []);

        if (empty($items)) {
            throw new \Exception('No se encontraron videos en esta playlist, o el ID/URL es incorrecto.');
        }

        foreach ($items as $index => $item) {
            $snippet = $item['snippet'];

            Video::updateOrCreate(
                ['video_id' => $snippet['resourceId']['videoId']],
                [
                    'module_id' => $module->id,
                    'title' => $snippet['title'],
                    'description' => $snippet['description'],
                    'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'],
                    'order_index' => $index,
                ]
            );
        }

        return count($items);
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
