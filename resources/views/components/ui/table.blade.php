{{-- Wraps a table so it scrolls horizontally on narrow screens instead
     of forcing the page to scroll. Use with x-ui.th / x-ui.td. --}}
@props(['head' => null])

<div class="overflow-x-auto">
    <table {{ $attributes->class('w-full text-left text-sm') }}>
        @isset($head)
            <thead class="border-b border-ink-200 bg-ink-50">
                <tr>{{ $head }}</tr>
            </thead>
        @endisset

        <tbody class="divide-y divide-ink-200">
            {{ $slot }}
        </tbody>
    </table>
</div>
