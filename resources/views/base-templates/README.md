# Base templates

A base template is a **design**: the markup, styling and choreography of an
invitation. A *theme* (a row in `templates`) picks one and supplies its own
media, colours and timings. An *invitation* picks a theme and supplies the
couple, the date and the content.

Everything a design needs to describe itself lives in its own directory. There
is no list of designs to register it in, no shared constant to extend, and no
form to teach about its fields — adding a design is adding a directory.

## The directory

```
resources/views/base-templates/<name>/
├── show.blade.php     required — the page itself
├── template.php       the manifest: what this design is and declares
└── partials/          whatever show.blade.php wants to split out
```

`show.blade.php` is what makes a directory a design. A directory without one is
ignored, which is what lets a half-built design sit in the tree without
appearing in the studio.

`<name>` is the directory name and the value stored in `templates.base_template`.
Renaming it orphans existing rows, so it needs a migration — see
`2026_08_05_214745_rename_lux_wedding_base_template.php` for the shape of one.

## The manifest

`template.php` returns an array. Every section is optional; a design that
declares nothing still renders, it just offers the studio nothing to tune.

```php
return [
    'label'    => 'Test Graduation',   // what the studio calls it
    'category' => 'Graduation',        // groups the catalogue; defaults to "Other"

    'palette' => [
        'paper'  => ['label' => 'Page background', 'default' => '#ffffff'],
        'tassel' => ['label' => 'Tassel',          'default' => '#123456'],
    ],

    'settings' => [
        'capThrowMs' => [
            'label'   => 'Cap throw',              // labelled ⇒ the studio can change it
            'hint'    => 'ms before the caps go up.',
            'default' => 4200,
            'min'     => 0,
            'max'     => 9000,
        ],

        // No label ⇒ the design's own business. It still reaches the page as
        // CONFIG, but no field is drawn and no request can set it.
        'settleMs' => ['default' => 77],
    ],

    'groups' => [ /* slots edited together — see luxury-wedding-v1 */ ],
    'slots'  => [ /* the design's own content fields */ ],
];
```

### palette

The colour roles this design paints with. They reach the page as CSS custom
properties and are edited on the Colours card.

`paper`, `paper_deep`, `ink`, `ink_soft`, `gold`, `gold_deep` and `curtain` are
the shared vocabulary — the roles `VideoPaletteExtractor` produces, so
"Generate from video" fills exactly those. A role named outside that set works
fine; it just will not be filled automatically.

### settings

Numbers the design animates on, handed to the page as `CONFIG`. Declare `step`
to make one a decimal (`duckTo` is the only one today); everything else is a
whole number. `min`/`max` become the validation bounds *and* the input's
attributes.

The label rule is the whole of the editable/internal distinction: if the design
gave a timing a name the studio would understand, the studio can change it.

### slots and groups

The content an invitation can write for this design — see
`luxury-wedding-v1/template.php`, which is commented in full. One declaration
drives the invitation form, the text-asset library and the guest page together.

## What the page receives

`show.blade.php` is rendered with `$invitation` and `$template`. From those:

| Expression | What it is |
|---|---|
| `$template->palette()` | this design's roles, theme overrides merged in |
| `$template->settings()` | this design's timings, cast to real numbers |
| `$invitation->slot('motto')` | a declared slot, falling back to the design's default |
| `$invitation->localeList()` | languages this invitation is issued in |
| `$invitation->programmeRows()` | the evening's schedule for the current locale |

Both `palette()` and `settings()` are filtered to what the design declares, so
a stored value for a role or timing it dropped is left behind rather than
handed to a page with no use for it.

## Things a design must do

- **`@include('public._draft-notice')`** — or an unpublished preview looks
  identical to a live invitation.
- **Set `og:image` from `$invitation->og_image`** when present, and *not*
  declare `summary_large_image` when absent — see
  `luxury-wedding-v1/partials/_meta.blade.php`.
- **Respect `prefers-reduced-motion`** for anything that moves on its own.

## Checking it

`SecondBaseTemplateTest` builds a whole design at runtime — its own category,
palette role and timing — and asserts the studio picks all of it up. It is the
fastest way to see what the machinery guarantees, and worth reading before
adding a design.

Nothing needs registering for a new design to be discovered, but the manifests
are parsed once per request and cached in `BaseTemplates`. A test that writes
one at runtime must call `BaseTemplates::flush()`.
