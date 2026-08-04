@props(['name'])

@error($name)
    <p class="mt-1.5 flex items-center gap-1 text-xs text-bad-600">
        <x-heroicon-o-exclamation-circle class="h-3.5 w-3.5 shrink-0" />
        {{ $message }}
    </p>
@enderror
