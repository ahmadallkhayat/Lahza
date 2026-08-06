{{-- Copies a value to the clipboard and confirms it did.

     Sending links is the whole job here, so the shareable URL is one click
     away rather than something to select out of the page by hand.

     The icon swaps to a tick for a moment: without feedback there is no way
     to tell a successful copy from a dead button. `navigator.clipboard` needs
     a secure context — production is HTTPS and localhost counts as secure, so
     the failure branch is only reachable over plain http on a remote host. --}}
@props(['value', 'label' => 'Copy link'])

<div
    x-data="{
        copied: false,
        async copy() {
            try {
                await navigator.clipboard.writeText(@js($value));
                this.copied = true;
                setTimeout(() => this.copied = false, 1500);
            } catch (e) {
                window.prompt('Copy this link', @js($value));
            }
        },
    }"
    class="inline-flex"
>
    <button
        type="button"
        @click="copy()"
        :title="copied ? 'Copied' : @js($label)"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
        :class="copied ? 'text-ok-600' : 'text-ink-500 hover:bg-ink-100 hover:text-ink-900'"
    >
        <x-heroicon-o-clipboard-document class="h-[1.05rem] w-[1.05rem]" x-show="! copied" />
        <x-heroicon-o-check class="h-[1.05rem] w-[1.05rem]" x-show="copied" x-cloak />
        <span class="sr-only" x-text="copied ? 'Copied' : @js($label)">{{ $label }}</span>
    </button>
</div>
