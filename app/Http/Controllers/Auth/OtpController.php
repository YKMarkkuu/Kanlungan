<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\SendCodeRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Models\User;
use App\Services\OtpService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function sendCode(SendCodeRequest $request): JsonResponse
    {
        // Email is already trimmed + lowercased by prepareForValidation().
        OtpService::send($request->validated('email'));

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
     * Design A: verify the code and create the account in one request.
     * No transaction (one insert + one delete). If a unique-key race ever
     * fires, catch QueryException here later. Does not log the user in.
     *
     * display_name is optional. When blank, name falls back to the email local
     * part and display_name stays null. On success a flash message is set for
     * the login page, which the client redirects to.
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

        User::create([
            'name' => $name,
            'email' => $email,
            'display_name' => $displayName,
            'student_id' => $data['student_id'],
            'campus' => 'pampanga',
            'verified_at' => now(),
        ]);

        OtpService::invalidate($email);

        // Shown on the login page after the client redirects to /.
        session()->flash('status', 'Welcome to Kanlungan. Sign in to continue.');

        return response()->json(['status' => 'registered'], 201);
    }
}
