@props(['name' => null, 'type' => 'text'])

<input
    type="{{ $type }}"
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    @error($name) aria-invalid="true" @enderror
    {{ $attributes->except('id')->class([
        'block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm',
        'placeholder:text-ink-400',
        'focus:border-brand-500 focus:ring-brand-500',
        'disabled:bg-ink-50 disabled:text-ink-500',
        'border-bad-400' => $errors->has($name),
    ]) }}
>
