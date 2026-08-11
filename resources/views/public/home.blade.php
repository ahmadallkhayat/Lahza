@php
    use App\Support\SiteSettings;

    $offers = collect(SiteSettings::list('offers'))
        ->filter(fn ($o) => filled($o['title'] ?? null) || filled($o['body'] ?? null));

    // Every way of getting in touch that has actually been filled in. Built as
    // one list so the section can be skipped whole when there is none.
    $whatsapp = preg_replace('/\D+/', '', SiteSettings::text('contact_whatsapp'));

    $contacts = collect([
        [
            'icon' => 'phone',
            'label' => SiteSettings::text('contact_phone'),
            'href' => SiteSettings::text('contact_phone')
                ? 'tel:'.preg_replace('/[^\d+]+/', '', SiteSettings::text('contact_phone'))
                : null,
        ],
        [
            'icon' => 'chat-bubble-left-right',
            'label' => $whatsapp ? 'WhatsApp' : '',
            'href' => $whatsapp ? 'https://wa.me/'.$whatsapp : null,
        ],
        [
            'icon' => 'envelope',
            'label' => SiteSettings::text('contact_email'),
            'href' => SiteSettings::text('contact_email')
                ? 'mailto:'.SiteSettings::text('contact_email')
                : null,
        ],
        [
            'icon' => 'camera',
            'label' => SiteSettings::text('contact_instagram')
                ? '@'.ltrim(SiteSettings::text('contact_instagram'), '@')
                : '',
            'href' => SiteSettings::text('contact_instagram')
                ? 'https://instagram.com/'.ltrim(SiteSettings::text('contact_instagram'), '@')
                : null,
        ],
        [
            'icon' => 'map-pin',
            'label' => SiteSettings::text('contact_address'),
            'href' => null,
        ],
    ])->filter(fn ($c) => filled($c['label']));
@endphp

<x-layouts.public>
    {{-- ── Opening ──────────────────────────────────────────────────── --}}
    <section class="relative overflow-hidden">
        {{-- A wash rather than a photograph: the designs below carry the
             imagery, and a hero image would compete with the very thing the
             page is trying to show off. --}}
        <div
            aria-hidden="true"
            class="pointer-events-none absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,theme(colors.brand.100),transparent_70%)]"
        ></div>

        <div class="relative mx-auto max-w-3xl px-5 py-20 text-center sm:py-28">
            <div class="mb-8 flex justify-center">
                <x-ui.brand size="h-16" />
            </div>

            <h1 class="text-balance text-4xl font-semibold tracking-tight text-ink-900 sm:text-5xl">
                {{ $settings['hero_heading'] }}
            </h1>

            @if (filled($settings['hero_body']))
                <p class="mx-auto mt-5 max-w-xl text-pretty text-base leading-relaxed text-ink-600">
                    {{ $settings['hero_body'] }}
                </p>
            @endif

            <div class="mt-9 flex flex-wrap items-center justify-center gap-3">
                @auth
                    <x-ui.button :href="auth()->user()->isAdmin() ? route('admin.dashboard') : route('account.dashboard')">
                        {{ auth()->user()->isAdmin() ? 'Open the studio' : 'My invitations' }}
                    </x-ui.button>
                @else
                    <x-ui.button :href="route('register')">
                        {{ $settings['hero_cta'] }}
                    </x-ui.button>
                @endauth

                @if ($designs->isNotEmpty())
                    <x-ui.button href="#designs" variant="secondary">See the designs</x-ui.button>
                @endif
            </div>
        </div>
    </section>

    {{-- ── What we make ─────────────────────────────────────────────── --}}
    @if ($offers->isNotEmpty())
        <section class="border-t border-ink-200 bg-ink-50/50">
            <div class="mx-auto max-w-6xl px-5 py-16 sm:py-20">
                @if (filled($settings['offer_heading']))
                    <h2 class="text-center text-2xl font-semibold tracking-tight text-ink-900">
                        {{ $settings['offer_heading'] }}
                    </h2>
                @endif

                <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($offers as $offer)
                        <div class="rounded-2xl border border-ink-200 bg-white p-6">
                            @if (filled($offer['title'] ?? null))
                                <h3 class="text-base font-semibold text-ink-900">{{ $offer['title'] }}</h3>
                            @endif

                            @if (filled($offer['body'] ?? null))
                                <p class="mt-2 text-sm leading-relaxed text-ink-600">{{ $offer['body'] }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── The designs ──────────────────────────────────────────────── --}}
    @if ($designs->isNotEmpty())
        <section id="designs" class="scroll-mt-20 border-t border-ink-200">
            <div class="mx-auto max-w-6xl px-5 py-16 sm:py-20">
                <div class="text-center">
                    @if (filled($settings['designs_heading']))
                        <h2 class="text-2xl font-semibold tracking-tight text-ink-900">
                            {{ $settings['designs_heading'] }}
                        </h2>
                    @endif

                    @if (filled($settings['designs_body']))
                        <p class="mx-auto mt-3 max-w-xl text-sm leading-relaxed text-ink-600">
                            {{ $settings['designs_body'] }}
                        </p>
                    @endif
                </div>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($designs as $design)
                        @php($p = $design->palette())

                        <figure class="overflow-hidden rounded-2xl border border-ink-200 bg-white">
                            {{-- The theme's own clip, muted and looping. It is
                                 the product, so it is what the card shows —
                                 and preload="none" keeps a page of six of
                                 them from costing a visitor twenty megabytes
                                 before they have scrolled to it. --}}
                            <div class="aspect-[4/5] w-full overflow-hidden" style="background: {{ $p['curtain'] }}">
                                <video
                                    class="h-full w-full object-cover"
                                    src="{{ $design->intro_video }}"
                                    muted
                                    loop
                                    playsinline
                                    autoplay
                                    preload="none"
                                    aria-label="{{ $design->name }}"
                                ></video>
                            </div>

                            <figcaption class="flex items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-ink-900">{{ $design->name }}</p>
                                    <p class="truncate text-xs text-ink-500">{{ $design->baseTemplateLabel() }}</p>
                                </div>

                                {{-- The theme's palette, which is the part a
                                     couple actually chooses between. --}}
                                <div class="flex shrink-0 gap-1" aria-hidden="true">
                                    @foreach (['paper', 'gold', 'gold_deep', 'ink'] as $role)
                                        <span
                                            class="h-4 w-4 rounded-full ring-1 ring-black/10"
                                            style="background: {{ $p[$role] ?? '#ffffff' }}"
                                        ></span>
                                    @endforeach
                                </div>
                            </figcaption>
                        </figure>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- ── Contact ──────────────────────────────────────────────────── --}}
    @if ($contacts->isNotEmpty() || filled($settings['contact_body']))
        <section id="contact" class="scroll-mt-20 border-t border-ink-200 bg-ink-50/50">
            <div class="mx-auto max-w-3xl px-5 py-16 text-center sm:py-20">
                @if (filled($settings['contact_heading']))
                    <h2 class="text-2xl font-semibold tracking-tight text-ink-900">
                        {{ $settings['contact_heading'] }}
                    </h2>
                @endif

                @if (filled($settings['contact_body']))
                    <p class="mx-auto mt-3 max-w-lg text-sm leading-relaxed text-ink-600">
                        {{ $settings['contact_body'] }}
                    </p>
                @endif

                @if ($contacts->isNotEmpty())
                    <div class="mt-8 flex flex-wrap items-center justify-center gap-3">
                        @foreach ($contacts as $contact)
                            @if ($contact['href'])
                                <a
                                    href="{{ $contact['href'] }}"
                                    @if (str_starts_with($contact['href'], 'http')) target="_blank" rel="noopener" @endif
                                    class="inline-flex items-center gap-2 rounded-full border border-ink-200 bg-white px-4 py-2 text-sm text-ink-700 transition-colors hover:border-ink-300 hover:text-ink-900"
                                >
                                    <x-dynamic-component :component="'heroicon-o-'.$contact['icon']" class="h-4 w-4 text-ink-400" />
                                    {{ $contact['label'] }}
                                </a>
                            @else
                                <span class="inline-flex items-center gap-2 rounded-full border border-ink-200 bg-white px-4 py-2 text-sm text-ink-700">
                                    <x-dynamic-component :component="'heroicon-o-'.$contact['icon']" class="h-4 w-4 text-ink-400" />
                                    {{ $contact['label'] }}
                                </span>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>
        </section>
    @endif
</x-layouts.public>
