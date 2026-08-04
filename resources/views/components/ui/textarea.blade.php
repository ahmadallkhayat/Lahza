@props(['name' => null, 'rows' => 4])

<textarea
    rows="{{ $rows }}"
    @if ($name) name="{{ $name }}" id="{{ $attributes->get('id', $name) }}" @endif
    {{ $attributes->except('id')->class([
        'block w-full rounded-lg border-ink-300 bg-white text-sm text-ink-800 shadow-sm',
        'placeholder:text-ink-400',
        'focus:border-brand-500 focus:ring-brand-500',
        'border-bad-400' => $errors->has($name),
    ]) }}
>{{ $slot }}</textarea>
