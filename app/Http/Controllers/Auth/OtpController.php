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

        User::create([
            'name' => $data['display_name'],
            'email' => $email,
            'display_name' => $data['display_name'],
            'student_id' => $data['student_id'],
            'campus' => 'pampanga',
            'verified_at' => now(),
        ]);

        OtpService::invalidate($email);

        return response()->json(['status' => 'registered'], 201);
    }
}
