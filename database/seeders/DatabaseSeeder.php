<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PackageSeeder::class);

        User::factory()->create([
            'name' => 'Gise Braier',
            'email' => 'admin@user.com',
            'password' => Hash::make('asdasd'),
            'role' => 1,
        ]);

        Module::create([
            'name' => 'all',
            'description' => 'Every video available.',
        ]);

        Artisan::call('youtube:import', [
            'playlistId' => 'PLLNFRn-GVWb0Z-FdpMU3bHoVOIOPufyg5',
            'moduleId' => 1,
        ]);
    }
}
