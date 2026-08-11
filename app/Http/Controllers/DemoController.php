<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Support\DemoInvitation;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;

/**
 * A design, working, with nobody's wedding in it.
 *
 * The landing page used to advertise each theme with a bare <video> of its
 * intro clip, which shows the footage and none of the thing being sold — the
 * opening, the names arriving, the programme, the countdown. This renders the
 * real base template instead, exactly as a guest would receive it, filled with
 * invented details.
 *
 * Used twice: framed in a phone on the landing page, and opened full-screen by
 * "See it in action". Both are the same page, so what a visitor plays with is
 * what they would actually be buying.
 */
class DemoController extends Controller
{
    public function __invoke(Template $template): Response
    {
        // Only a theme that is actually on offer. A deactivated one is not
        // advertised on the landing page and should not be reachable by
        // guessing its slug either.
        abort_unless($template->is_active && $template->isRenderable(), 404);

        $invitation = DemoInvitation::for($template);

        App::setLocale($invitation->locale());

        return response()
            ->view($template->viewName(), compact('invitation', 'template'))
            // Randomised per request, so there is nothing here worth caching
            // and a cached copy would defeat the point of randomising it.
            ->header('Cache-Control', 'no-store')
            // Every demo is the same page with different names in it. Letting
            // a search engine index six of them would bury the real
            // invitations under near-duplicates of a sample.
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
