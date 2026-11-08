<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Verify the emailed code and start a session.
     *
     * Same response convention as OtpController: verification outcomes are
     * HTTP 200 with a `status`; only validation failures are 422. No
     * "remember me": the session cookie lifetime comes from config/session.php.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        // Email is already trimmed + lowercased by prepareForValidation().
        $email = $request->validated('email');

        $result = OtpService::verify($email, $request->validated('code'));

        if ($result['status'] !== 'ok') {
            return response()->json(['status' => $result['status']], 200);
        }

        $user = User::where('email', $email)->first();

        if ($user === null) {
            // SECURITY NOTE: not_registered confirms an email is unregistered to
            // anyone holding a valid OTP for it. That requires inbox access, so
            // it's accepted for now (same tradeoff as email_taken in
            // OtpController::register). Revisit in the security pass.
            // The OTP is deliberately left in place; it expires or locks out normally.
            return response()->json(['status' => 'not_registered'], 200);
        }

        Auth::login($user, false);

        // Session fixation protection.
        $request->session()->regenerate();

        // The code is spent; don't allow replay.
        OtpService::invalidate($email);

        return response()->json([
            'status' => 'authenticated',
            'redirect' => $user->has_completed_onboarding ? route('feed') : route('onboarding'),
        ], 200);
    }

    public function home(Request $request): RedirectResponse
    {
        // Staff (admin / guidance / moderator) never use the student app: they go to their own dashboard.
        if (in_array($request->user()->role, \App\Services\ModerationService::STAFF, true)) {
            return redirect()->route('staff.dashboard');
        }

        return redirect()->route($request->user()->has_completed_onboarding ? 'feed' : 'onboarding');
    }

    public function onboarding(Request $request)
    {
        if ($request->user()->has_completed_onboarding) {
            return redirect()->route('feed');
        }

        return response()->view('onboarding');
    }

    public function completeOnboarding(Request $request): RedirectResponse
    {
        $request->user()->forceFill(['has_completed_onboarding' => true])->save();

        return redirect()->route('feed');
    }

    public function logout(Request $request): \Illuminate\Http\JsonResponse|RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'logged_out'], 200);
        }

        return redirect('/')->with('status', 'You have been signed out.');
    }
}
