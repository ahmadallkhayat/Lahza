<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Support\EventCalendar;
use Carbon\Carbon;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * The guest-facing invitation. Read-only and unauthenticated.
 */
class InvitationController extends Controller
{
    public function show(Invitation $invitation): Response
    {
        $this->guard($invitation);

        $invitation->load('template');
        $this->applyLocale($invitation);

        abort_unless($invitation->template->isRenderable(), 500,
            'This invitation points at a template view that does not exist.');

        return response()->view($invitation->template->viewName(), [
            'invitation' => $invitation,
            'template' => $invitation->template,
        ]);
    }

    /** For Apple Calendar, Outlook and anything else that speaks .ics. */
    public function calendar(Invitation $invitation): Response
    {
        $this->guard($invitation);

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
     * Render in the invitation's language, not the studio's, so labels and
     * translated month names match what the guest is reading.
     */
    private function applyLocale(Invitation $invitation): void
    {
        App::setLocale($invitation->locale);
        Carbon::setLocale($invitation->locale === 'ku' ? 'ckb' : $invitation->locale);
    }
}
