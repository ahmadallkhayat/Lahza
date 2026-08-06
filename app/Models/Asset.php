<?php

namespace App\Models;

use App\Support\BaseTemplates;
use App\Support\Placeholders;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable piece of an invitation: an intro clip, a music track, or a block
 * of ready-written text such as a Quran verse or a hosting line.
 *
 * Media assets are picked from a dropdown when editing a template, so the same
 * clip is stored once rather than re-uploaded per theme. Text assets are
 * inserted into the invitation's content fields, with their placeholders
 * filled from that invitation's names.
 *
 * An asset with no `base_template` is shared by every design.
 */
class Asset extends Model
{
    use HasFactory;

    public const KIND_VIDEO = 'video';

    public const KIND_MUSIC = 'music';

    public const KIND_TEXT = 'text';

    /** @var array<string, string> */
    public const KINDS = [
        self::KIND_VIDEO => 'Intro video',
        self::KIND_MUSIC => 'Music',
        self::KIND_TEXT => 'Text',
    ];

    /** Kinds that hold a file rather than a body. */
    public const MEDIA_KINDS = [self::KIND_VIDEO, self::KIND_MUSIC];

    /**
     * What a text asset is meant for. Only a hint — the studio can insert any
     * block into any field, since a blessing that was filed under "verse" is
     * still a perfectly good hosting line.
     *
     * @var array<string, string>
     */
    public const TEXT_FIELDS = [
        'verse' => 'Verse or blessing',
        'hosts' => 'Hosting line',
        'venue_name' => 'Venue',
        'venue_city' => 'City',
        'programme' => 'Programme step',
    ];

    /** Where each kind's uploads live on the public disk. */
    public const DIRECTORIES = [
        self::KIND_VIDEO => 'assets/videos',
        self::KIND_MUSIC => 'assets/music',
    ];

    protected $fillable = [
        'base_template',
        'kind',
        'name',
        'description',
        'path',
        'body',
        'locale',
        'field',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /* ── scopes ───────────────────────────────────────────────────── */

    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /**
     * Assets a given base template can use — its own, plus the shared ones.
     *
     * A null $baseTemplate means "no design chosen yet", which still has the
     * shared library available to it.
     */
    public function scopeForBaseTemplate(Builder $query, ?string $baseTemplate): void
    {
        $query->where(function (Builder $q) use ($baseTemplate) {
            $q->whereNull('base_template');

            if ($baseTemplate !== null) {
                $q->orWhere('base_template', $baseTemplate);
            }
        });
    }

    /** Shared first, then by design, then by name — the order the studio reads. */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('base_template is null desc')
            ->orderBy('base_template')
            ->orderBy('name');
    }

    /* ── kind ─────────────────────────────────────────────────────── */

    public function isMedia(): bool
    {
        return in_array($this->kind, self::MEDIA_KINDS, true);
    }

    public function isText(): bool
    {
        return $this->kind === self::KIND_TEXT;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    /** Public disk directory for this asset's uploads. */
    public function directory(): string
    {
        return self::DIRECTORIES[$this->kind] ?? 'assets/other';
    }

    /* ── presentation ─────────────────────────────────────────────── */

    /** Human label for the design this belongs to, or the shared library. */
    public function baseTemplateLabel(): string
    {
        if ($this->base_template === null) {
            return 'Shared — all templates';
        }

        return BaseTemplates::available()[$this->base_template] ?? $this->base_template;
    }

    public function localeLabel(): ?string
    {
        return $this->locale
            ? config("templates.locales.{$this->locale}.label", $this->locale)
            : null;
    }

    public function fieldLabel(): ?string
    {
        return $this->field ? (self::TEXT_FIELDS[$this->field] ?? $this->field) : null;
    }

    public function isRtl(): bool
    {
        return (bool) config("templates.locales.{$this->locale}.rtl", false);
    }

    /**
     * The body with its placeholders filled from an invitation, or from the
     * sample values when there is no invitation yet.
     *
     * @param  array<string, string|null>|null  $values
     */
    public function render(?array $values = null): string
    {
        return Placeholders::render($this->body, $values ?? Placeholders::samples());
    }

    /* ── media ownership ──────────────────────────────────────────── */

    /**
     * Whether the library owns the file behind a public URL.
     *
     * This is what stops a template from deleting a shared file. A template
     * that uploaded its own clip owns it and may delete it on replacement; one
     * that merely points at an asset must not, or swapping the video on one
     * theme would empty the library for every other.
     */
    public static function ownsPath(?string $url): bool
    {
        return filled($url) && static::where('path', $url)->exists();
    }

    /**
     * Templates currently pointing at this asset's file.
     *
     * @return Collection<int, Template>
     */
    public function templatesInUse()
    {
        if (blank($this->path)) {
            return Template::whereRaw('1 = 0')->get();
        }

        return Template::query()
            ->where('intro_video', $this->path)
            ->orWhere('music', $this->path)
            ->get();
    }
}
