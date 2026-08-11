<x-layouts.admin title="Site settings" width="form">
    <x-ui.page-header
        title="Site settings"
        description="What the public page says, and how customers reach you."
    >
        <x-slot:actions>
            <x-ui.button :href="route('home')" variant="secondary" icon="arrow-top-right-on-square" target="_blank">
                View site
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <form method="POST" action="{{ route('admin.settings.update') }}" class="space-y-6">
        @csrf
        @method('PUT')

        <x-ui.card title="Studio" description="Leave a field empty to use the wording the site ships with.">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Name" name="brand_name">
                    <x-ui.input name="brand_name" :value="old('brand_name', $settings['brand_name'])" />
                </x-ui.field>

                <x-ui.field label="Tagline" name="tagline">
                    <x-ui.input name="tagline" :value="old('tagline', $settings['tagline'])" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Opening" description="The first thing anyone reads.">
            <div class="space-y-5">
                <x-ui.field label="Heading" name="hero_heading">
                    <x-ui.input name="hero_heading" :value="old('hero_heading', $settings['hero_heading'])" />
                </x-ui.field>

                <x-ui.field label="Body" name="hero_body">
                    <x-ui.textarea name="hero_body" rows="3">{{ old('hero_body', $settings['hero_body']) }}</x-ui.textarea>
                </x-ui.field>

                <x-ui.field label="Button" name="hero_cta" hint="Takes a visitor to sign-up.">
                    <x-ui.input name="hero_cta" :value="old('hero_cta', $settings['hero_cta'])" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="What you offer" description="One card each. Empty rows are dropped when you save.">
            <div x-data="settingsForm(@js(['offers' => old('offers', $settings['offers'])]))" class="space-y-4">
                <template x-for="(offer, i) in offers" :key="i">
                    <div class="rounded-xl border border-ink-200 p-4">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="text-xs font-semibold uppercase tracking-wider text-ink-400">
                                Item <span x-text="i + 1"></span>
                            </p>

                            <div class="flex items-center gap-1">
                                <button
                                    type="button"
                                    x-on:click="move(i, -1)"
                                    class="rounded-md p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700"
                                    aria-label="Move up"
                                >
                                    <x-heroicon-o-arrow-up class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    x-on:click="move(i, 1)"
                                    class="rounded-md p-1 text-ink-400 hover:bg-ink-100 hover:text-ink-700"
                                    aria-label="Move down"
                                >
                                    <x-heroicon-o-arrow-down class="h-4 w-4" />
                                </button>
                                <button
                                    type="button"
                                    x-on:click="remove(i)"
                                    class="rounded-md p-1 text-ink-400 hover:bg-bad-50 hover:text-bad-600"
                                    aria-label="Remove"
                                >
                                    <x-heroicon-o-trash class="h-4 w-4" />
                                </button>
                            </div>
                        </div>

                        <div class="space-y-3">
                            <input
                                type="text"
                                :name="`offers[${i}][title]`"
                                x-model="offer.title"
                                placeholder="Title"
                                class="block w-full rounded-lg border-ink-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            >
                            <textarea
                                :name="`offers[${i}][body]`"
                                x-model="offer.body"
                                rows="2"
                                placeholder="A sentence about it"
                                class="block w-full rounded-lg border-ink-300 text-sm shadow-sm focus:border-brand-500 focus:ring-brand-500"
                            ></textarea>
                        </div>
                    </div>
                </template>

                <x-ui.button type="button" variant="secondary" size="sm" icon="plus" x-on:click="add()">
                    Add item
                </x-ui.button>
            </div>

            <div class="mt-5 border-t border-ink-200 pt-5">
                <x-ui.field label="Section heading" name="offer_heading">
                    <x-ui.input name="offer_heading" :value="old('offer_heading', $settings['offer_heading'])" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Designs" description="Which themes the site shows off.">
            <div class="space-y-5">
                <x-ui.field label="Section heading" name="designs_heading">
                    <x-ui.input name="designs_heading" :value="old('designs_heading', $settings['designs_heading'])" />
                </x-ui.field>

                <x-ui.field label="Section body" name="designs_body">
                    <x-ui.textarea name="designs_body" rows="2">{{ old('designs_body', $settings['designs_body']) }}</x-ui.textarea>
                </x-ui.field>

                <div>
                    <x-ui.label>Featured themes</x-ui.label>
                    <p class="mb-2.5 text-xs text-ink-500">
                        Tick none to show every active theme, newest first.
                    </p>

                    @php($featured = old('featured_templates', $settings['featured_templates']))

                    @if ($templates->isEmpty())
                        <p class="text-sm text-ink-500">No active themes yet.</p>
                    @else
                        <div class="space-y-1.5">
                            @foreach ($templates as $t)
                                <label class="flex items-center gap-2.5 text-sm text-ink-700">
                                    <input
                                        type="checkbox"
                                        name="featured_templates[]"
                                        value="{{ $t->id }}"
                                        @checked(in_array($t->id, (array) $featured))
                                        class="rounded border-ink-300 text-brand-600 focus:ring-brand-500"
                                    >
                                    {{ $t->name }}
                                    <span class="text-xs text-ink-400">{{ $t->baseTemplateLabel() }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endif
                </div>

                <x-ui.field
                    label="Most to show"
                    name="featured_limit"
                    hint="Only applies when nothing is ticked above."
                >
                    <x-ui.input
                        type="number"
                        name="featured_limit"
                        min="1"
                        max="24"
                        :value="old('featured_limit', $settings['featured_limit'])"
                    />
                </x-ui.field>
            </div>
        </x-ui.card>

        <x-ui.card title="Contact" description="Anything left empty is simply not shown.">
            <div class="space-y-5">
                <x-ui.field label="Section heading" name="contact_heading">
                    <x-ui.input name="contact_heading" :value="old('contact_heading', $settings['contact_heading'])" />
                </x-ui.field>

                <x-ui.field label="Section body" name="contact_body">
                    <x-ui.textarea name="contact_body" rows="2">{{ old('contact_body', $settings['contact_body']) }}</x-ui.textarea>
                </x-ui.field>

                <div class="grid gap-5 sm:grid-cols-2">
                    <x-ui.field label="Phone" name="contact_phone">
                        <x-ui.input name="contact_phone" :value="old('contact_phone', $settings['contact_phone'])" placeholder="+964 770 000 0000" />
                    </x-ui.field>

                    <x-ui.field label="WhatsApp" name="contact_whatsapp" hint="Digits only; becomes a wa.me link.">
                        <x-ui.input name="contact_whatsapp" :value="old('contact_whatsapp', $settings['contact_whatsapp'])" placeholder="9647700000000" />
                    </x-ui.field>

                    <x-ui.field label="Email" name="contact_email">
                        <x-ui.input type="email" name="contact_email" :value="old('contact_email', $settings['contact_email'])" />
                    </x-ui.field>

                    <x-ui.field label="Instagram" name="contact_instagram" hint="Handle, without the @.">
                        <x-ui.input name="contact_instagram" :value="old('contact_instagram', $settings['contact_instagram'])" placeholder="lahza" />
                    </x-ui.field>
                </div>

                <x-ui.field label="Address" name="contact_address">
                    <x-ui.input name="contact_address" :value="old('contact_address', $settings['contact_address'])" />
                </x-ui.field>

                <x-ui.field label="Footer note" name="footer_note">
                    <x-ui.input name="footer_note" :value="old('footer_note', $settings['footer_note'])" />
                </x-ui.field>
            </div>
        </x-ui.card>

        <div class="flex items-center gap-3">
            <x-ui.button type="submit">Save settings</x-ui.button>
        </div>
    </form>
</x-layouts.admin>
