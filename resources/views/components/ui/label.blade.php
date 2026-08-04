@props(['for' => null, 'required' => false])

<label @if($for) for="{{ $for }}" @endif
       {{ $attributes->class('mb-1.5 block text-xs font-medium text-ink-700') }}>
    {{ $slot }}
    @if ($required)
        <span class="text-bad-600" aria-hidden="true">*</span>
    @endif
</label>
