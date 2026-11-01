<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\ModerationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnforceRestrictions;
use App\Http\Middleware\RequireRole;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Community routes: feed, create post, post + comments
|--------------------------------------------------------------------------
| Loaded from routes/web.php with one line, so it already has the "web"
| middleware. Kept in its own file so it never conflicts with the login /
| register routes in web.php.
|
| Login-only: signed-out visitors are sent to the login page.
| Data is stored in the posts / comments / reactions tables.
*/

Route::middleware(['auth', EnforceRestrictions::class])->group(function () {
    Route::get('/feed', [FeedController::class, 'index'])->name('feed');

    Route::get('/post/create', [PostController::class, 'create'])->name('post.create');
    Route::post('/post', [PostController::class, 'store'])->middleware('throttle:10,1')->name('post.store');
    Route::get('/post/{id}', [PostController::class, 'show'])->whereNumber('id')->name('post.show');
    Route::delete('/post/{id}', [PostController::class, 'destroy'])->whereNumber('id')->middleware('throttle:10,1')->name('post.destroy');
    Route::post('/post/{id}/comments', [PostController::class, 'comment'])->whereNumber('id')->middleware('throttle:20,1')->name('post.comment');
    Route::delete('/post/{id}/comments/{commentId}', [PostController::class, 'destroyComment'])->whereNumber(['id', 'commentId'])->middleware('throttle:10,1')->name('comment.destroy');

    Route::post('/post/{id}/comments/{commentId}/accept', [PostController::class, 'acceptComment'])->whereNumber(['id', 'commentId'])->name('comment.accept');

    Route::post('/post/{id}/like', [PostController::class, 'likePost'])->whereNumber('id')->middleware('throttle:60,1')->name('post.like');
    Route::post('/post/{id}/comments/{commentId}/like', [PostController::class, 'likeComment'])->whereNumber(['id', 'commentId'])->middleware('throttle:60,1')->name('comment.like');
    Route::post('/post/{id}/comments/{commentId}/helpful', [PostController::class, 'helpfulComment'])->whereNumber(['id', 'commentId'])->middleware('throttle:60,1')->name('comment.helpful');

    // Reports + community vote
    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::post('/report/{type}/{id}', [ReportController::class, 'store'])->whereIn('type', ['post', 'comment'])->whereNumber('id')->middleware('throttle:10,1')->name('report.store');
    Route::post('/reports/{report}/start', [ReportController::class, 'start'])->whereNumber('report')->middleware('throttle:20,1')->name('report.start');
    Route::post('/reports/{report}/vote', [ReportController::class, 'vote'])->whereNumber('report')->middleware('throttle:30,1')->name('report.vote');

    // Profile: points + history
    Route::get('/me', [ProfileController::class, 'show'])->name('community.me');

    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:10,1')->name('profile.avatar');
    Route::get('/me/posts', [ProfileController::class, 'posts'])->name('community.me.posts');
    Route::get('/me/comments', [ProfileController::class, 'comments'])->name('community.me.comments');
    Route::post('/me/name', [ProfileController::class, 'updateName'])->name('community.me.name');

    // Search + alerts (design screens 3 and 7)
    Route::get('/search', [SearchController::class, 'index'])->name('search');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
    Route::post('/alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');
    Route::get('/alerts/{id}', [AlertController::class, 'open'])->whereNumber('id')->name('alerts.open');

    // Static pages
    Route::get('/rules', [PageController::class, 'rules'])->name('page.rules');
    Route::get('/privacy', [PageController::class, 'privacy'])->name('page.privacy');
    Route::get('/help', [PageController::class, 'help'])->name('page.help');

    // Moderator queue (design screen 9): moderator, admin and guidance accounts only
    Route::middleware(RequireRole::class.':moderator,admin,guidance')->group(function () {
        Route::get('/moderation', [ModerationController::class, 'index'])->name('moderation');
        Route::post('/moderation/{report}', [ModerationController::class, 'act'])->whereNumber('report')->middleware('throttle:60,1')->name('moderation.act');
    });

    Route::post('/moderation/{report}/unmask', [ModerationController::class, 'unmask'])
        ->whereNumber('report')
        ->middleware(RequireRole::class.':admin,dpo')
        ->name('moderation.unmask');
});

Route::middleware('auth')->group(function () {
    // Blocking and unblocking remain available to authenticated restricted users.
    Route::post('/block/{type}/{id}', [BlockController::class, 'store'])->whereIn('type', ['post', 'comment'])->whereNumber('id')->middleware('throttle:20,1')->name('block.store');
    Route::post('/blocks/{block}/unblock', [BlockController::class, 'destroy'])->whereNumber('block')->middleware('throttle:20,1')->name('block.destroy');
});
