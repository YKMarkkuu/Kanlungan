<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\DebugMailLogsController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\ProfileController;
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

Route::get('/debug-mail-logs', DebugMailLogsController::class)
    ->middleware('throttle:5,1')
    ->name('debug.mail-logs');

Route::middleware(['auth', RejectDeletedAccounts::class])->group(function () {
    Route::post('/auth/logout', [LoginController::class, 'logout'])->name('auth.logout');
    Route::get('/home', [LoginController::class, 'home'])->name('home');
    Route::get('/onboarding', [LoginController::class, 'onboarding'])->name('onboarding');
    Route::post('/onboarding/complete', [LoginController::class, 'completeOnboarding'])->name('onboarding.complete');
    Route::post('/onboarding/restart', [ProfileController::class, 'restartOnboarding'])->name('onboarding.restart');

    Route::post('/me/delete', [ProfileController::class, 'destroyAccount'])
        ->middleware('throttle:3,1,account-delete')
        ->name('community.me.delete');

    require __DIR__.'/community.php';
});
