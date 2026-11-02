<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RejectDeletedAccounts
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user === null || $user->deleted_at === null) {
            return $next($request);
        }

        Auth::logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'This account was deleted.'], 401);
        }

        return redirect('/');
    }
}
