<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The studio is the studio's.
 *
 * Signing in is now open to anyone, so `auth` alone no longer means "one of
 * us" — it means "somebody". Every route under /admin needs this behind it or
 * a customer who signed up through the landing page could read the whole
 * template library and every other customer's invitations by typing the URL.
 *
 * 404 rather than 403, for the same reason an unpublished invitation is a 404:
 * a refusal confirms there is something there to be refused.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user()?->isAdmin(), 404);

        return $next($request);
    }
}
