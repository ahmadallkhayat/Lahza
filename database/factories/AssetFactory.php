<?php

namespace Database\Factories;

use App\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /** Text by default — it needs no file on disk to be a valid row. */
    public function definition(): array
    {
        return [
            'base_template' => null,
            'kind' => Asset::KIND_TEXT,
            'name' => 'Asset '.Str::random(5),
            'description' => null,
            'path' => null,
            'body' => 'تتشرف عائلة {groom} وعائلة {bride} بدعوتكم',
            'locale' => 'ar',
            'translation' => null,
            'reference' => null,
            'field' => 'hosts',
            'is_active' => true,
        ];
    }

    public function video(): static
    {
        return $this->state(fn () => [
            'kind' => Asset::KIND_VIDEO,
            'path' => '/storage/assets/videos/'.Str::lower(Str::random(8)).'.mp4',
            'body' => null,
            'locale' => null,
            'field' => null,
        ]);
    }

    public function music(): static
    {
        return $this->state(fn () => [
            'kind' => Asset::KIND_MUSIC,
            'path' => '/storage/assets/music/'.Str::lower(Str::random(8)).'.mp3',
            'body' => null,
            'locale' => null,
            'field' => null,
        ]);
    }

    /** A religious verse: the verse, its translation and its reference. */
    public function verse(): static
    {
        return $this->state(fn () => [
            'kind' => Asset::KIND_VERSE,
            'body' => 'وَمِنْ آيَاتِهِ أَنْ خَلَقَ لَكُم مِّنْ أَنفُسِكُمْ أَزْوَاجًا',
            'translation' => ['en' => 'And among His signs…'],
            'reference' => ['ar' => 'سورة الروم — ٢١', 'en' => 'QURAN 30:21'],
            'locale' => null,
            'field' => null,
        ]);
    }

    /** Scoped to one design rather than shared by all of them. */
    public function forBase(string $baseTemplate = 'luxury-wedding-v1'): static
    {
        return $this->state(fn () => ['base_template' => $baseTemplate]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
