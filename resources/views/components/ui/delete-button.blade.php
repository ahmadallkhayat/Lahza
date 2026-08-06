{{-- Delete as an icon, with the confirm naming what is about to go.

     "Delete this template?" gives no way to notice you are on the wrong row;
     the name is passed through @js so a name containing a quote — or Arabic —
     cannot break out of the string.

     When `reason` is given the action is disabled instead of submitted, so a
     refusal that the controller would make anyway is explained up front
     rather than after a round trip. --}}
@props([
    'action',
    'confirm',
    'label' => 'Delete',
    'reason' => null,
])

@if ($reason)
    <x-ui.icon-button icon="trash" :label="$reason" tone="danger" disabled />
@else
    <form method="POST" action="{{ $action }}" onsubmit="return confirm({{ Js::from($confirm) }})">
        @csrf
        @method('DELETE')
        <x-ui.icon-button type="submit" icon="trash" :label="$label" tone="danger" />
    </form>
@endif
