{{-- Label + control + hint + error in one place, so every form row
     is spaced and styled identically. --}}
@props(['label' => null, 'name' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->only('class') }}>
    @if ($label)
        <x-ui.label :for="$name" :required="$required">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1.5 text-xs text-ink-500">{{ $hint }}</p>
    @endif

    @if ($name)
        <x-ui.error :name="$name" />
    @endif
</div>
