<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    /** Reveal timings, applied when a template leaves any of them unset. */
    public const DEFAULT_SETTINGS = [
        'keepVideoAudioMs' => 0,
        'musicFadeMs' => 1600,
        'fadeOutLeadMs' => 800,
        'crossfadeMs' => 1400,
        'duckTo' => 0.42,
        'fogHz' => 700,
        'duckMs' => 2600,
        'revealFallbackMs' => 13000,
    ];

    /** Colour roles every design expects to be able to reference. */
    public const DEFAULT_PALETTE = [
        'paper' => '#f7f1e8',
        'paper_deep' => '#ede1d0',
        'ink' => '#2e1d16',
        'ink_soft' => '#6b5648',
        'gold' => '#a8874f',
        'gold_deep' => '#856a3c',
        'curtain' => '#1a0f0d',
    ];

    protected $fillable = [
        'name',
        'slug',
        'category',
        'view',
        'description',
        'intro_video',
        'music',
        'palette',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'palette' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
    ];

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Palette merged over the defaults, so a design never renders with a
     * missing colour if a new role is added later.
     *
     * @return array<string, string>
     */
    public function palette(): array
    {
        return array_merge(self::DEFAULT_PALETTE, $this->palette ?? []);
    }

    /**
     * Timings merged over the defaults, for the same reason.
     *
     * @return array<string, int|float>
     */
    public function settings(): array
    {
        return array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
    }

    /** Blade view that renders this design. */
    public function viewName(): string
    {
        return 'templates.'.$this->view.'.show';
    }

    /** Whether the Blade file this record points at actually exists. */
    public function isRenderable(): bool
    {
        return view()->exists($this->viewName());
    }

    /** Human label for the category, from config. */
    public function categoryLabel(): string
    {
        return config('templates.categories.'.$this->category, $this->category);
    }
}
