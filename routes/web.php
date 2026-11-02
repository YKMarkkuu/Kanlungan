<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
use App\Http\Middleware\PreventAuthenticatedResponseCaching;
use App\Http\Middleware\RejectDeletedAccounts;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

// Guest-only pages: a logged-in user is redirected to RouteServiceProvider::HOME (/home).
Route::view('/', 'auth.login')->middleware('guest')->name('login');
Route::redirect('/login', '/')->middleware('guest');
Route::view('/register', 'auth.register')->middleware('guest')->name('register');

Route::get('/rules', [PageController::class, 'rules'])->name('page.rules');
Route::get('/privacy', [PageController::class, 'privacy'])->name('page.privacy');

Route::post('/auth/send-code',   [OtpController::class, 'sendCode'])->middleware('throttle:otp-send')->name('auth.send-code');
Route::post('/auth/verify-code', [OtpController::class, 'verifyCode'])->middleware('throttle:otp-verify')->name('auth.verify-code');
Route::post('/auth/register',    [OtpController::class, 'register'])->middleware('throttle:otp-register')->name('auth.register');

Route::post('/auth/login',  [LoginController::class, 'login'])->middleware('throttle:otp-verify')->name('auth.login');

Route::middleware(['auth', RejectDeletedAccounts::class, PreventAuthenticatedResponseCaching::class])->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::get('/home', fn () => redirect()->route('feed'))->name('home');

    Route::post('/me/delete', [ProfileController::class, 'destroyAccount'])
        ->middleware('throttle:3,1,account-delete')
        ->name('community.me.delete');

    require __DIR__.'/community.php';
});
