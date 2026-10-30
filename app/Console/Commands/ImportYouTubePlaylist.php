<?php

namespace App\Console\Commands;

use App\Models\Module;
use App\Services\YouTubeImportService;
use Illuminate\Console\Command;

class ImportYouTubePlaylist extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'youtube:import {playlistId} {moduleId}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import YouTube playlist into the database for a specific module';

    /**
     * Execute the console command.
     */
    public function handle(YouTubeImportService $importer)
    {
        $module = Module::find($this->argument('moduleId'));

        if (! $module) {
            $this->error("Module with ID {$this->argument('moduleId')} not found.");

            return self::FAILURE;
        }

        try {
            $count = $importer->importPlaylist($this->argument('playlistId'), $module);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Successfully imported {$count} videos to module {$module->name}!");

        return self::SUCCESS;
    }
}
