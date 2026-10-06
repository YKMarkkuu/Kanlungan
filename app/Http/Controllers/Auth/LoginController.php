<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        return response()->json(['status' => 'authenticated'], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['status' => 'logged_out'], 200);
    }
}
