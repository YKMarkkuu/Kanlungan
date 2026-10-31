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
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Your account is suspended.'], 403);
            }

            return response()->view('community.restricted', [], 403);
        }

        if ($user->muted_until !== null && ! $request->isMethodSafe()) {
            $until = Carbon::parse($user->muted_until);

            if ($until->isFuture()) {
                $message = 'You are muted until '.$until->format('M j, g:i A').'. You can read, but not post, comment, like, report or vote.';

                if ($request->expectsJson()) {
                    return response()->json(['message' => $message, 'errors' => ['report' => [$message]]], 422);
                }

                return back()->withErrors(['report' => $message]);
            }
        }

        return $next($request);
    }
}
