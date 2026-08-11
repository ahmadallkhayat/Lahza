<?php

use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\InvitationController as CustomerInvitationController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\AssetController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvitationController as AdminInvitationController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\PaletteController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\TemplateController;
use App\Http\Controllers\Admin\TemplatePreviewController;
use App\Http\Controllers\Admin\TranslateController;
use App\Http\Controllers\Auth\LoginController as CustomerLoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::get('/', HomeController::class)->name('home');

// A design working, filled with invented details — framed in a phone on the
// landing page and opened full-screen by "See it in action". The same page
// both times, so what a visitor plays with is what they would be buying.
Route::get('/demo/{template:slug}', DemoController::class)->name('templates.demo');

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
| Accounts
|--------------------------------------------------------------------------
|
| Open sign-up, and the customer's own side of the app. Everything here is
| scoped to the signed-in account by the controllers — see the note on
| Account\InvitationController about why that is done in the query.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('login', [CustomerLoginController::class, 'create'])->name('login');
    Route::post('login', [CustomerLoginController::class, 'store'])->name('login.store');

    Route::get('register', [RegisterController::class, 'create'])->name('register');
    Route::post('register', [RegisterController::class, 'store'])
        // Sign-up writes a row and is reachable by anyone at all.
        ->middleware('throttle:10,1')
        ->name('register.store');
});

Route::post('logout', [CustomerLoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::prefix('account')->name('account.')->middleware('auth')->group(function () {
    Route::get('/', AccountDashboardController::class)->name('dashboard');

    Route::get('profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('profile/password', [ProfileController::class, 'updatePassword'])
        ->middleware('throttle:10,1')
        ->name('profile.password');

    Route::resource('invitations', CustomerInvitationController::class)->except('show');
    Route::patch('invitations/{invitation}/publish', [CustomerInvitationController::class, 'togglePublish'])
        ->name('invitations.publish');
});

/*
|--------------------------------------------------------------------------
| Studio
|--------------------------------------------------------------------------
|
| Everything behind /admin. Sign-in is open to anyone now, so `auth` alone
| no longer means "one of us" — the `admin` middleware is what does, and
| every route past the login pair carries it.
|
*/

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

        Route::get('/', DashboardController::class)->name('dashboard');

        // Before the resource routes so the literal segment is not read as a
        // {template} id. Throttled because each call runs ffmpeg.
        Route::post('templates/palettes', PaletteController::class)
            ->middleware('throttle:20,1')
            ->name('templates.palettes');

        // Renders the invitation from unsaved form values, for the live frame.
        Route::post('templates/preview', TemplatePreviewController::class)
            ->name('templates.preview');

        Route::resource('templates', TemplateController::class)->except('show');

        // The reusable library — clips, music and ready-written text — that
        // templates and invitations pick from.
        Route::resource('assets', AssetController::class)->except('show');

        Route::resource('invitations', AdminInvitationController::class)->except('show');
        Route::patch('invitations/{invitation}/publish', [AdminInvitationController::class, 'togglePublish'])
            ->name('invitations.publish');

        // Fills the other languages from the English content. Throttled
        // because every call goes out to a third-party API.
        Route::post('translate', TranslateController::class)
            ->middleware('throttle:20,1')
            ->name('translate');

        // The people who have accounts, and how many invitations each holds.
        Route::resource('customers', CustomerController::class);

        // What the public site says about the studio.
        Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');

        // The signed-in account's own settings. No {user} segment by design —
        // there is one account, and it is always the authenticated one.
        Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
        Route::put('account', [AccountController::class, 'update'])->name('account.update');
        Route::put('account/password', [AccountController::class, 'updatePassword'])
            ->middleware('throttle:10,1')
            ->name('account.password');
    });
});
