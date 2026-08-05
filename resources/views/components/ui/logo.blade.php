{{-- The Lahza mark.

     Renders resources/svg/lahza-logo.svg, which carries fill="currentColor",
     so the mark takes the colour of whatever text context it sits in — set
     `text-brand-600` on it or a parent and it goes teal.

     The mark contains the wordmark as letterforms, so it needs an accessible
     name; screen readers would otherwise announce nothing at all. --}}
@props(['label' => 'Lahza'])

<span {{ $attributes->class('inline-flex text-brand-600') }} role="img" aria-label="{{ $label }}">
    <x-icon-lahza-logo class="h-full w-auto" aria-hidden="true" />
</span>
