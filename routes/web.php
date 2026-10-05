<?php

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

Route::view('/', 'auth.login')->name('login');
Route::redirect('/login', '/');
Route::view('/register', 'auth.register')->name('register');

Route::post('/auth/send-code',   [OtpController::class, 'sendCode'])->middleware('throttle:otp-send')->name('auth.send-code');
Route::post('/auth/verify-code', [OtpController::class, 'verifyCode'])->middleware('throttle:otp-verify')->name('auth.verify-code');
Route::post('/auth/register',    [OtpController::class, 'register'])->middleware('throttle:otp-register')->name('auth.register');
