<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Support\EventCalendar;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * The guest-facing invitation. Read-only and unauthenticated.
 *
 * An invitation can be issued in several languages, each with its own link, so
 * a specific language can be sent to the guests who read it.
 */
class InvitationController extends Controller
{
    public function show(Invitation $invitation, ?string $locale = null): Response
    {
        $this->guard($invitation);

        // An unknown or unenabled locale in the URL is not an error: fall back
        // to the invitation's default rather than 404 on a mistyped link.
        $invitation->load('template')->renderIn($locale ?? $invitation->defaultLocale());

        $this->applyLocale($invitation);

        abort_unless($invitation->template->isRenderable(), 500,
            'This invitation points at a base template that does not exist.');

        return response()->view($invitation->template->viewName(), [
            'invitation' => $invitation,
            'template' => $invitation->template,
        ]);
    }

    /** For Apple Calendar, Outlook and anything else that speaks .ics. */
    public function calendar(Invitation $invitation, ?string $locale = null): Response
    {
        $this->guard($invitation);

        $invitation->renderIn($locale ?? $invitation->defaultLocale());
        $this->applyLocale($invitation);

        return response(EventCalendar::ics($invitation), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="'.$invitation->slug.'.ics"',
        ]);
    }

    /**
     * Unpublished invitations are invisible rather than forbidden — a 403 would
     * confirm the slug exists to anyone guessing.
     */
    private function guard(Invitation $invitation): void
    {
        abort_unless($invitation->published || auth()->check(), 404);
    }

    /**
     * Render in the language of the link, not the studio's, so labels and
     * translated month names match what the guest is reading.
     */
    private function applyLocale(Invitation $invitation): void
    {
        App::setLocale($invitation->locale());
        Carbon::setLocale($invitation->locale() === 'ku' ? 'ckb' : $invitation->locale());
    }
}
