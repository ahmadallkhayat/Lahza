{{-- Label + control + hint + error in one place, so every form row is
     spaced and styled identically.

     `name` is the validation key (dotted, e.g. settings.fogHz) while `for`
     is the control's id. They differ for array inputs, so `for` can be set
     separately; it falls back to `name` for the common case. --}}
@props(['label' => null, 'name' => null, 'for' => null, 'hint' => null, 'required' => false])

<div {{ $attributes->only('class') }}>
    @if ($label)
        <x-ui.label :for="$for ?? $name" :required="$required">{{ $label }}</x-ui.label>
    @endif

    {{ $slot }}

    @if ($hint)
        <p class="mt-1.5 text-xs text-ink-500">{{ $hint }}</p>
    @endif

    @if ($name)
        <x-ui.error :name="$name" />
    @endif
</div>
