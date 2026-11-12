<?php

namespace App\Http\Middleware;

use App\Services\ModerationService;
use Closure;
use Illuminate\Http\Request;

/**
 * Staff do not use the student onboarding flow, but may access shared community routes such as /me and
 * the legacy moderation queue.
 */
class StudentsOnly
{
    public function handle(Request $request, Closure $next)
    {
        $role = $request->user()?->role;

        if ($role === 'dpo') {
            abort_unless($request->isMethodSafe(), 403);

            return redirect()->route('staff.dpo-reveal');
        }

        if (in_array($role, ModerationService::STAFF, true)
            && $request->routeIs('onboarding', 'onboarding.complete', 'onboarding.restart')) {
            if ($request->isMethodSafe()) {
                return redirect()->route('staff.dashboard');
            }

            abort(403);
        }

        return $next($request);
    }
}
