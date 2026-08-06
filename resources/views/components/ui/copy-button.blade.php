{{-- Copies a value to the clipboard and confirms it did.

     Sending links is the whole job here, so the shareable URL is one click
     away rather than something to select out of the page by hand.

     The behaviour lives in resources/js/admin/copy-button.js — see the note
     there on why no JavaScript is written inline in x-data. --}}
@props(['value', 'label' => 'Copy link'])

<div x-data="copyButton(@js($value), @js($label))" class="inline-flex">
    <button
        type="button"
        x-on:click="copy()"
        x-bind:title="title"
        class="inline-flex h-8 w-8 items-center justify-center rounded-lg transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-500"
        x-bind:class="copied ? 'text-ok-600' : 'text-ink-500 hover:bg-ink-100 hover:text-ink-900'"
    >
        <x-heroicon-o-clipboard-document class="h-[1.05rem] w-[1.05rem]" x-show="! copied" />
        <x-heroicon-o-check class="h-[1.05rem] w-[1.05rem]" x-show="copied" x-cloak />
        <span class="sr-only" x-text="title">{{ $label }}</span>
    </button>
</div>
