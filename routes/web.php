<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvitationController;
use App\Http\Controllers\Admin\LoginController;
use App\Http\Controllers\Admin\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public
|--------------------------------------------------------------------------
*/

Route::view('/', 'public.coming-soon')->name('home');

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

        Route::get('templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
    });
});
