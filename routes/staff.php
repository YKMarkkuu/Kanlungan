<?php

use App\Http\Controllers\Staff\AuthController;
use App\Http\Controllers\Staff\CaseController;
use App\Http\Controllers\Staff\DashboardController;
use App\Http\Middleware\RequireRole;
use App\Services\ModerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Staff routes: dashboard for admin, guidance (and the older moderator role)
|--------------------------------------------------------------------------
| Staff only ever see REPORTED posts and comments (see StaffDashboardService). They sign in on their own
| page with an emailed code, and only for accounts created with `php artisan staff:create`. The student
| pages (routes/community.php) are closed to staff accounts, and these pages are closed to students.
*/

// Sign-in (signed-out visitors only).
Route::middleware('guest')->prefix('staff')->group(function () {
    Route::get('/login', [AuthController::class, 'show'])->name('staff.login');
    Route::post('/login/code', [AuthController::class, 'sendCode'])->middleware('throttle:otp-send')->name('staff.send-code');
    Route::post('/login/password', [AuthController::class, 'devLogin'])->middleware('throttle:otp-verify')->name('staff.dev-login');
    Route::post('/login/verify', [AuthController::class, 'verify'])->middleware('throttle:otp-verify')->name('staff.verify');
});

Route::middleware(['auth', RequireRole::class.':'.implode(',', ModerationService::STAFF)])->group(function () {
    Route::post('/staff/logout', [AuthController::class, 'logout'])->name('staff.logout');

    Route::prefix('staff')->group(function () {
        Route::get('/', [DashboardController::class, 'overview'])->name('staff.dashboard');
        Route::get('/reports', [CaseController::class, 'index'])->name('staff.reports');
        Route::get('/reports/{report}', [CaseController::class, 'show'])->whereNumber('report')->name('staff.case');
        Route::post('/reports/{report}/act', [CaseController::class, 'act'])->whereNumber('report')->middleware('throttle:60,1')->name('staff.act');
        Route::post('/reports/{report}/reveal', [CaseController::class, 'reveal'])
            ->whereNumber('report')
            ->middleware([RequireRole::class.':'.implode(',', ModerationService::REVEAL_ROLES), 'throttle:30,1'])
            ->name('staff.reveal');
        Route::get('/sanctions', [DashboardController::class, 'sanctions'])->name('staff.sanctions');
        Route::get('/activity', [DashboardController::class, 'activity'])->name('staff.activity');
    });

    // Old links (earlier alerts pointed here) keep working.
    Route::get('/moderation', fn (Request $request) => redirect()->route('staff.reports', [
        'view' => $request->query('tab') === 'guidance' ? 'referred' : 'review',
    ]))->name('moderation');
});
