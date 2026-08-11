<?php

namespace App\Models;

use App\Support\BaseTemplates;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Template extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'base_template',
        'description',
        'intro_video',
        'ambient_video',
        'music',
        'boomerang_intro',
        'boomerang_intro_path',
        'palette',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'palette' => 'array',
        'settings' => 'array',
        'is_active' => 'boolean',
        'boomerang_intro' => 'boolean',
    ];

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /**
     * Palette merged over this design's own defaults, so a theme never
     * renders with a missing colour if a role is added to the design later.
     *
     * Which roles exist is the design's declaration, not a global list: a
     * theme built on a design that paints with four colours gets four, and
     * one stored role the design has since dropped is left behind rather
     * than handed to a page that has no use for it.
     *
     * @return array<string, string>
     */
    public function palette(): array
    {
        $defaults = BaseTemplates::paletteDefaults($this->base_template);

        return array_merge($defaults, array_intersect_key($this->palette ?? [], $defaults));
    }

    /**
     * Timings merged over this design's own defaults, for the same reason,
     * and cast to real numbers.
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
        $defaults = BaseTemplates::settingDefaults($this->base_template);
        $merged = array_merge($defaults, array_intersect_key($this->settings ?? [], $defaults));

        $cast = [];

        foreach ($merged as $key => $value) {
            $cast[$key] = is_float($defaults[$key] ?? 0)
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
