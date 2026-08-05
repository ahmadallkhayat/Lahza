<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TranslateController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::view('/', 'public.coming-soon')->name('home');

// The guest-facing invitation. Read-only, unauthenticated, and 404s while the
// invitation is unpublished.
//
// /i/{slug}          the invitation's default language
// /i/{slug}/{locale} a specific language, so one link per language can be sent
//
// The locale segment is constrained so it cannot swallow "calendar.ics".
Route::get('/i/{invitation}', [InvitationController::class, 'show'])->name('invitations.show');
Route::get('/i/{invitation}/calendar.ics', [InvitationController::class, 'calendar'])->name('invitations.calendar');
Route::get('/i/{invitation}/{locale}', [InvitationController::class, 'show'])
    ->whereIn('locale', ['ar', 'ku', 'en'])
    ->name('invitations.show.locale');
Route::get('/i/{invitation}/{locale}/calendar.ics', [InvitationController::class, 'calendar'])
    ->whereIn('locale', ['ar', 'ku', 'en'])
    ->name('invitations.calendar.locale');

/*
|--------------------------------------------------------------------------
| Studio
|--------------------------------------------------------------------------
|
| Everything behind /admin. There is no registration route by design —
| accounts come from the seeder or the console.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware('auth')->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        Route::resource('templates', TemplateController::class)->except('show');

        Route::resource('invitations', AdminInvitationController::class)->except('show');
        Route::patch('invitations/{invitation}/publish', [AdminInvitationController::class, 'togglePublish'])
            ->name('invitations.publish');

        // Fills the other languages from the English content. Throttled
        // because every call goes out to a third-party API.
        Route::post('translate', TranslateController::class)
            ->middleware('throttle:20,1')
            ->name('translate');
    });
});
