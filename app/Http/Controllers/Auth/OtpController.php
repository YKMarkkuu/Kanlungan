<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendCodeRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class OtpController extends Controller
{
    public function sendCode(SendCodeRequest $request): JsonResponse
    {
        // Email is already trimmed + lowercased by prepareForValidation().
        if (! OtpService::send($request->validated('email'))) {
            return response()->json(['status' => 'delivery_failed'], 503);
        }

        return response()->json(['status' => 'sent'], 200);
    }

    /**
     * Verification outcomes are always HTTP 200; the client reads `status`.
     * Only validation failures (422) use a 4xx code.
     */
    public function verifyCode(VerifyCodeRequest $request): JsonResponse
    {
        $result = OtpService::verify(
            $request->validated('email'),
            $request->validated('code'),
        );

        return response()->json(['status' => $result['status']], 200);
    }

    /**
     * Design A: verify the code, create the account and log the user in, in one request.
     * No transaction (one insert + one delete). If a unique-key race ever
     * fires, catch QueryException here later.
     *
     * The terms checkbox is validated before OTP verification, and the server
     * records the timestamp and current terms version.
     *
     * display_name is optional. When blank, name falls back to the email local
     * part and display_name stays null. On success the session is started (same
     * as LoginController::login) and the client is sent straight to onboarding.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();
        $email = $data['email'];

        $result = OtpService::verify($email, $data['code']);

        if ($result['status'] !== 'ok') {
            return response()->json(['status' => $result['status']], 200);
        }

        if (User::where('email', $email)->exists()) {
            return response()->json(['status' => 'email_taken'], 422);
        }

        if (User::where('student_id', $data['student_id'])->exists()) {
            return response()->json(['status' => 'student_id_taken'], 422);
        }

        $displayName = filled($data['display_name'] ?? null) ? $data['display_name'] : null;

        // TODO: name column is vestigial; replace or drop once user profiles ship.
        $name = $displayName ?? explode('@', $email)[0];

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'display_name' => $displayName,
            'student_id' => $data['student_id'],
            'campus' => 'pampanga',
            'verified_at' => now(),
            'accepted_terms_at' => now(),
            'terms_version' => config('kanlungan.terms_version'),
        ]);

        // The code is spent; don't allow replay.
        OtpService::invalidate($email);

        // The OTP was just verified, so the student has already proven inbox access.
        // No "remember me", same as login. Regenerate to prevent session fixation.
        Auth::login($user, false);
        $request->session()->regenerate();

        // has_completed_onboarding defaults to false, so /onboarding will render.
        return response()->json([
            'status' => 'registered',
            'redirect' => route('onboarding'),
        ], 201);
    }
}
