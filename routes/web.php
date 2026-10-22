<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpController;
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

Route::post('/auth/send-code',   [OtpController::class, 'sendCode'])->middleware('throttle:otp-send')->name('auth.send-code');
Route::post('/auth/verify-code', [OtpController::class, 'verifyCode'])->middleware('throttle:otp-verify')->name('auth.verify-code');
Route::post('/auth/register',    [OtpController::class, 'register'])->middleware('throttle:otp-register')->name('auth.register');

Route::post('/auth/login',  [LoginController::class, 'login'])->middleware('throttle:otp-verify')->name('auth.login');
Route::post('/auth/logout', [LoginController::class, 'logout'])->middleware('auth')->name('auth.logout');

Route::get('/home', fn () => redirect()->route('feed'))->middleware('auth')->name('home');

require __DIR__.'/community.php';
