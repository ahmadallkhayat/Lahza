<?php

namespace Database\Factories;

use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Template '.Str::random(5);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => 'lux-wedding',
            'view' => 'lux-wedding',
            'description' => null,
            'intro_video' => '/storage/templates/videos/test.mp4',
            'music' => '/storage/templates/music/test.mp3',
            'palette' => null,
            'settings' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
