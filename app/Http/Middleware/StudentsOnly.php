<?php

namespace App\Http\Middleware;

use App\Services\ModerationService;
use Closure;
use Illuminate\Http\Request;

/**
 * The community (feed, posts, comments, search, profile) is for students only.
 *
 * Admin, guidance and moderator accounts work from /staff and only ever see reported content, so they are
 * sent back there instead of browsing the student feed. This keeps the space safe: staff are not reading
 * everyone's venting posts.
 */
class StudentsOnly
{
    public function handle(Request $request, Closure $next)
    {
        $role = $request->user()?->role;

        if (in_array($role, ModerationService::STAFF, true)) {
            abort_unless($request->isMethodSafe(), 403);

            return redirect()->route('staff.dashboard');
        }

        return $next($request);
    }
}
