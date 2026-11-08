<?php

use App\Http\Controllers\AlertController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Middleware\EnforceRestrictions;
use App\Http\Middleware\StudentsOnly;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Community routes: feed, create post, post + comments
|--------------------------------------------------------------------------
| Loaded from routes/web.php with one line, so it already has the "web"
| middleware. Kept in its own file so it never conflicts with the login /
| register routes in web.php.
|
| Login-only: signed-out visitors are sent to the login page. Students only: staff accounts are
| redirected to /staff (see routes/staff.php and StudentsOnly).
| Data is stored in the posts / comments / reactions tables.
*/

Route::middleware(['auth', EnforceRestrictions::class, StudentsOnly::class])->group(function () {
    Route::get('/feed', [FeedController::class, 'index'])->name('feed');

    Route::get('/post/create', [PostController::class, 'create'])->name('post.create');
    Route::post('/post', [PostController::class, 'store'])->middleware('throttle:10,1')->name('post.store');
    Route::get('/post/{id}', [PostController::class, 'show'])->whereNumber('id')->name('post.show');
    Route::delete('/post/{id}', [PostController::class, 'destroy'])->whereNumber('id')->middleware('throttle:10,1')->name('post.destroy');
    Route::post('/post/{id}/comments', [PostController::class, 'comment'])->whereNumber('id')->middleware('throttle:20,1')->name('post.comment');
    Route::delete('/post/{id}/comments/{commentId}', [PostController::class, 'destroyComment'])->whereNumber(['id', 'commentId'])->middleware('throttle:10,1')->name('comment.destroy');

    Route::post('/post/{id}/comments/{commentId}/accept', [PostController::class, 'acceptComment'])->whereNumber(['id', 'commentId'])->name('comment.accept');

    Route::post('/post/{id}/like', [PostController::class, 'likePost'])->whereNumber('id')->middleware('throttle:60,1')->name('post.like');
    Route::post('/post/{id}/bookmark', [PostController::class, 'bookmarkPost'])->whereNumber('id')->middleware('throttle:60,1')->name('post.bookmark');
    Route::post('/post/{id}/comments/{commentId}/like', [PostController::class, 'likeComment'])->whereNumber(['id', 'commentId'])->middleware('throttle:60,1')->name('comment.like');
    Route::post('/post/{id}/comments/{commentId}/helpful', [PostController::class, 'helpfulComment'])->whereNumber(['id', 'commentId'])->middleware('throttle:60,1')->name('comment.helpful');

    // Reports + community vote
    Route::get('/reports', fn () => redirect()->route('alerts', ['tab' => 'verdicts']))->name('reports.index');
    Route::post('/report/{type}/{id}', [ReportController::class, 'store'])->whereIn('type', ['post', 'comment'])->whereNumber('id')->middleware('throttle:10,1')->name('report.store');
    Route::post('/reports/{report}/start', [ReportController::class, 'start'])->whereNumber('report')->middleware('throttle:20,1')->name('report.start');
    Route::post('/reports/{report}/vote', [ReportController::class, 'vote'])->whereNumber('report')->middleware('throttle:30,1')->name('report.vote');

    // Profile: points + history
    Route::get('/me', [ProfileController::class, 'show'])->name('community.me');

    Route::post('/profile/avatar', [ProfileController::class, 'updateAvatar'])->middleware('throttle:10,1')->name('profile.avatar');
    Route::get('/me/points', [ProfileController::class, 'points'])->name('community.me.points');
    Route::get('/me/posts', [ProfileController::class, 'posts'])->name('community.me.posts');
    Route::get('/me/comments', [ProfileController::class, 'comments'])->name('community.me.comments');
    Route::get('/me/saved', [ProfileController::class, 'saved'])->name('community.me.saved');
    Route::post('/me/name', [ProfileController::class, 'updateName'])->name('community.me.name');

    // Search + alerts (design screens 3 and 7)
    Route::get('/search', [SearchController::class, 'index'])->middleware('throttle:30,1')->name('search');
    Route::get('/alerts', [AlertController::class, 'index'])->name('alerts');
    Route::get('/alerts/unread-count', [AlertController::class, 'unreadCount'])->name('alerts.unread-count');
    Route::post('/alerts/read-all', [AlertController::class, 'readAll'])->name('alerts.read-all');
    Route::get('/alerts/{id}', [AlertController::class, 'open'])->whereNumber('id')->name('alerts.open');

    // Static pages
    Route::get('/help', [PageController::class, 'help'])->name('page.help');
});

Route::middleware('auth')->group(function () {
    // Blocking and unblocking remain available to authenticated restricted users.
    Route::post('/block/{type}/{id}', [BlockController::class, 'store'])->whereIn('type', ['post', 'comment'])->whereNumber('id')->middleware('throttle:20,1')->name('block.store');
    Route::post('/blocks/{block}/unblock', [BlockController::class, 'destroy'])->whereNumber('block')->middleware('throttle:20,1')->name('block.destroy');
});
