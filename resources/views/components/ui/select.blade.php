@props(['name' => null])

<select
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->except('id')->class([
        'block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm',
        'focus:border-brand-500 focus:ring-brand-500',
        'border-bad-400' => $errors->has($name),
    ]) }}
>
    {{ $slot }}
</select>
