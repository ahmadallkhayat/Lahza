<?php

namespace App\Models;

use App\Support\BaseTemplates;
use App\Support\ContentSlots;
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

    /**
     * A religious verse: the verse, its translation and its reference, held
     * together because they are shown together and are useless apart. Its own
     * kind rather than text filed under a category, which made it three
     * library rows to find and insert one at a time.
     */
    public const KIND_VERSE = 'verse';

    /**
     * Languages that print no translation beneath a verse.
     *
     * Arabic because the verse is Arabic — a translation of it into Arabic is a
     * field that can never hold anything meaningful. Kurdish because a Kurdish
     * invitation reads the verse in Arabic too, which is what lang/ku has
     * always assumed.
     *
     * One list, read by everything that has to agree about it: the asset editor
     * draws no box for these languages, the slot receiving a translation
     * declares the same exclusion, and the request drops them however posted.
     */
    public const VERSE_UNTRANSLATED = ['ar', 'ku'];

    /** @var array<string, string> */
    public const KINDS = [
        self::KIND_VIDEO => 'Intro video',
        self::KIND_MUSIC => 'Music',
        self::KIND_TEXT => 'Text',
        self::KIND_VERSE => 'Religious verse',
    ];

    /**
     * How each kind introduces itself on the type picker, and what its section
     * of the library is headed with.
     *
     * Held beside KINDS rather than in the view because three places need it —
     * the picker, the library's section headings, and the empty state — and a
     * fourth spelling of "Intro video" is how they drift apart.
     *
     * @var array<string, array{icon: string, blurb: string}>
     */
    public const KIND_META = [
        self::KIND_VIDEO => [
            'icon' => 'film',
            'blurb' => 'The clip that plays when a guest opens the invitation.',
        ],
        self::KIND_MUSIC => [
            'icon' => 'musical-note',
            'blurb' => 'The track that plays underneath, once the guest taps.',
        ],
        self::KIND_TEXT => [
            'icon' => 'document-text',
            'blurb' => 'A hosting line or other wording, with names filled in.',
        ],
        self::KIND_VERSE => [
            'icon' => 'book-open',
            'blurb' => 'A verse with its translation and reference, inserted as one.',
        ],
    ];

    /** Kinds that hold a file rather than a body. */
    public const MEDIA_KINDS = [self::KIND_VIDEO, self::KIND_MUSIC];

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
        'translation',
        'reference',
        'locale',
        'field',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'translation' => 'array',
        'reference' => 'array',
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

    /**
     * What a text asset is meant for. Only a hint — the studio can insert any
     * block into any field, since a blessing that was filed under "verse" is
     * still a perfectly good hosting line.
     *
     * Derived from the core invitation fields plus every slot the base
     * templates declare, rather than written out here. This used to be a hand
     * kept list, and it drifted: it offered "verse" and "programme step" as
     * places to file a block of text while the invitation form rendered
     * neither, so the library let you write a verse and gave it nowhere to go.
     * A list computed from what designs actually declare cannot say that.
     *
     * @return array<string, string>
     */
    public static function textFields(): array
    {
        return ContentSlots::all();
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

    public function isVerse(): bool
    {
        return $this->kind === self::KIND_VERSE;
    }

    /** Kinds whose content is written rather than uploaded. */
    public function isWritten(): bool
    {
        return $this->isText() || $this->isVerse();
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? $this->kind;
    }

    public function kindIcon(): string
    {
        return self::KIND_META[$this->kind]['icon'] ?? 'rectangle-stack';
    }

    /**
     * Which section of the library this belongs in.
     *
     * Media sections by kind, but text sections by what it is *for*: a verse
     * and a hosting line are both "text" the way a video and a track are both
     * "media", which is to say not usefully. One Text pile mixing them is the
     * same nesting problem the per-design grouping had, one level down.
     */
    public function sectionKey(): string
    {
        return $this->isText() ? 'text:'.($this->field ?: '') : $this->kind;
    }

    /** Whether this kind's section splits into one per category. */
    private static function sectionsByCategory(string $kind): bool
    {
        return $kind === self::KIND_TEXT;
    }

    /**
     * Every section the library can show, in reading order: the media kinds,
     * then one per text category, then anything filed under nothing.
     *
     * The text categories come from what the designs declare, so a section
     * exists exactly when there is somewhere on an invitation to put it.
     *
     * @return array<string, array{label: string, icon: string, kind: string, field: string|null}>
     */
    public static function sections(): array
    {
        $sections = [];

        // KINDS order throughout, with the text categories expanded in place
        // of the single Text entry — so the page reads clips, tracks, the text
        // categories, then verses, and never reshuffles.
        foreach (self::KINDS as $kind => $label) {
            $icon = self::KIND_META[$kind]['icon'];

            if (! self::sectionsByCategory($kind)) {
                $sections[$kind] = [
                    'label' => $label,
                    'icon' => $icon,
                    'kind' => $kind,
                    'field' => null,
                ];

                continue;
            }

            foreach (self::textFields() as $field => $fieldLabel) {
                $sections[$kind.':'.$field] = [
                    'label' => $fieldLabel,
                    'icon' => $icon,
                    'kind' => $kind,
                    'field' => $field,
                ];
            }

            // Only drawn when something is in it: text written before it was
            // clear what it was for.
            $sections[$kind.':'] = [
                'label' => 'Text — not filed',
                'icon' => $icon,
                'kind' => $kind,
                'field' => null,
            ];
        }

        return $sections;
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
        return $this->field ? (self::textFields()[$this->field] ?? $this->field) : null;
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
