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

        {{-- Row hover: with actions at the far right of a wide row, a highlight
             is what keeps your eye on the record you picked. --}}
        <tbody class="divide-y divide-ink-200 [&>tr]:transition-colors [&>tr:hover]:bg-ink-50/60">
            {{ $slot }}
        </tbody>
    </table>
</div>
