<?php

namespace Database\Factories;

use App\Models\Module;
use App\Models\Video;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Video>
 */
class VideoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'module_id' => Module::factory(),
            'title' => fake()->unique()->sentence(3),
            'description' => fake()->sentence(),
            'video_id' => fake()->unique()->bothify('yt-?????#####'),
            'thumbnail_url' => fake()->imageUrl(480, 360),
            'order_index' => fake()->numberBetween(0, 50),
        ];
    }
}
