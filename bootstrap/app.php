<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['admin' => EnsureUserIsAdmin::class]);

        // Signing in is open to anyone now, so a guest being sent somewhere
        // has to depend on where they were going: the studio's own sign-in
        // for /admin, the customer one for everything else. Sending a
        // customer to /admin/login would offer them a door they cannot open.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*')
                ? route('admin.login')
                : route('login'),
        );

        // And where an already-signed-in user lands is their own side of the
        // app, not a fixed page that half of them cannot reach.
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->user()?->isAdmin()
                ? route('admin.dashboard')
                : route('account.dashboard'),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // The skeleton only checks for api/* here, which silently turned the
        // studio's JSON endpoints into HTML redirects — a failed validation
        // came back as a 302 to the login page rather than a 422 with errors.
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
