<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Models\Video;
use Illuminate\Support\Facades\Http;

class ImportYouTubePlaylist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:import {playlistId} {categoryId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import YouTube playlist into the database for a specific category';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $playlistId = $this->argument('playlistId');
        $categoryId = $this->argument('categoryId');
        $apiKey = env('YOUTUBE_API_KEY');

        // 1. Verify the category exists
        $category = Category::find($categoryId);
        if (!$category) {
            $this->error("Category with ID {$categoryId} not found.");
            return;
        }

        // 2. Fetch data from the YouTube API
        $url = "https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=50&playlistId={$playlistId}&key={$apiKey}";
        $response = Http::get($url);

        if ($response->failed()) {
            $this->error("Failed to connect to YouTube API. Check your YOUTUBE_API_KEY.");
            return;
        }

        $items = $response->json('items', []);

        if (empty($items)) {
            $this->warn("No videos found in this playlist, or the ID is incorrect.");
            return;
        }

        // 3. Save the videos to the database
        foreach ($items as $index => $item) {
            $snippet = $item['snippet'];
            
            Video::updateOrCreate(
                ['video_id' => $snippet['resourceId']['videoId']],
                [
                    'category_id' => $categoryId,
                    'title' => $snippet['title'],
                    'description' => $snippet['description'],
                    'thumbnail_url' => $snippet['thumbnails']['high']['url'] ?? $snippet['thumbnails']['default']['url'],
                    'order_index' => $index,
                ]
            );
        }

        $this->info('Successfully imported ' . count($items) . ' videos to category ' . $category->name . '!');
    }
}
