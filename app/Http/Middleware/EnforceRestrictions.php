<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Banned users cannot use the community at all. Muted users can read, but any write
 * (post, comment, like, report, vote) is blocked until the mute ends.
 */
class EnforceRestrictions
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        if ($user->banned_at !== null) {
            return response()->view('community.restricted', [], 403);
        }

        if ($user->muted_until !== null && ! $request->isMethodSafe()) {
            $until = Carbon::parse($user->muted_until);

            if ($until->isFuture()) {
                return back()->withErrors(['report' => 'You are muted until '.$until->format('M j, g:i A').'. You can read, but not post, comment, like, report or vote.']);
            }
        }

        return $next($request);
    }
}
