<?php

namespace App\Models;

use App\Support\BaseTemplates;
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

        // How long before the clip ends the names appear. Larger than
        // fadeOutLeadMs on purpose: the difference is how long the names have
        // the video to themselves before it starts dissolving.
        'namesLeadMs' => 2600,

        // How long the softened backdrop has the screen to itself before the
        // rest of the invitation arrives. This is the gap that makes the blur
        // its own beat: the names sit over the clip, the clip softens, and only
        // then does the content assemble. Set it to 0 and the blur happens
        // behind arriving content, which is what it used to do.
        'blurLeadMs' => 1200,

        // How long petals keep falling after the reveal before thinning out.
        'petalsMs' => 22000,
        'crossfadeMs' => 1400,
        'duckTo' => 0.42,
        'fogHz' => 700,
        'duckMs' => 2600,
        // Longest the mark holds before the prompt appears regardless. A clip
        // that cannot be reached must not leave a guest waiting on a spinner.
        'loaderTimeoutMs' => 8000,

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
        'base_template',
        'description',
        'intro_video',
        'ambient_video',
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
     * Timings merged over the defaults, for the same reason, and cast to real
     * numbers.
     *
     * The casting is not cosmetic. Form input arrives as strings, and the
     * template's JavaScript compares one of these strictly: `"0" === 0` is
     * false, so a keepVideoAudioMs of 0 failed to mute the intro clip and its
     * own soundtrack played under the music. `"1400" + 200` would have
     * concatenated too. Numbers are guaranteed here rather than trusted from
     * whatever wrote the row.
     *
     * @return array<string, int|float>
     */
    public function settings(): array
    {
        $merged = array_merge(self::DEFAULT_SETTINGS, $this->settings ?? []);
        $cast = [];

        foreach ($merged as $key => $value) {
            $cast[$key] = is_float(self::DEFAULT_SETTINGS[$key] ?? 0)
                ? (float) $value
                : (int) $value;
        }

        return $cast;
    }

    /** Blade view of the base template this theme is built on. */
    public function viewName(): string
    {
        return BaseTemplates::view((string) $this->base_template);
    }

    /** Whether the base template this record points at actually exists. */
    public function isRenderable(): bool
    {
        return view()->exists($this->viewName());
    }

    /** Human label for the base template, e.g. "Lux Wedding". */
    public function baseTemplateLabel(): string
    {
        return BaseTemplates::available()[$this->base_template]
            ?? (string) $this->base_template;
    }
}
