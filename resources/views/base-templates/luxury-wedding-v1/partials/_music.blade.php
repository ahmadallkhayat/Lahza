{{-- Never autoplays: playback starts in the tap handler, both to respect
     browser autoplay policy and because the reveal is the moment the
     sound belongs to. --}}
@if ($template->music)
    <audio id="music" src="{{ $template->music }}" loop preload="auto"></audio>

    <button id="mute" type="button" aria-label="{{ __('invitation.mute') }}">
        <svg id="ic-on" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="M15.5 8.5a5 5 0 0 1 0 7"/><path d="M18.5 5.5a9 9 0 0 1 0 13"/></svg>
        <svg id="ic-off" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M11 5 6 9H2v6h4l5 4V5z"/><path d="m22 9-6 6M16 9l6 6"/></svg>
    </button>
@endif
