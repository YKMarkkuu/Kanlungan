<?php

namespace App\Services;

use App\Mail\OtpCodeMail;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

/**
 * Thin wrapper around OtpCode for sending and checking email codes.
 *
 * Static on purpose: no state, no injected dependencies, and it can be called
 * directly from tinker (OtpService::send(...)).
 *
 * Never logs or returns plaintext codes. Locally, MAIL_MAILER=log writes the
 * outgoing message (including the code) to storage/logs/laravel.log.
 */
class OtpService
{
    /**
     * Generate a fresh OTP for the email and mail it.
     */
    public static function send(string $email): bool
    {
        $email = self::normalize($email);

        $generated = OtpCode::generateForEmail($email);

        try {
            Mail::to($email)->send(new OtpCodeMail($generated['plain'], $email));
        } catch (TransportExceptionInterface $exception) {
            Log::error('OTP email delivery failed.', [
                'exception' => $exception,
            ]);

            return false;
        }

        return true;
    }

    /**
     * Check a submitted code against the active OTP for the email.
     *
     * Does NOT delete the row on success; the caller decides when to invalidate.
     *
     * @return array{status: 'ok'|'expired_or_missing'|'locked_out'|'mismatch'}
     */
    public static function verify(string $email, string $code): array
    {
        $email = self::normalize($email);

        $otp = OtpCode::findValidForEmail($email);

        if ($otp === null) {
            return ['status' => 'expired_or_missing'];
        }

        // Race: not atomic; acceptable while send() is unlimited. Revisit when rate limiting ships in Commit C.
        if ($otp->attempts >= OtpCode::MAX_ATTEMPTS) {
            // locked_out leaves the row in place by design: the caller may call
            // invalidate() for a hard lockout, or let the user request a new code
            // for a soft lockout. Current spec is soft lockout. docs/AUTH.md says
            // "invalidate on lockout" and needs a reconciliation pass (separate commit).
            return ['status' => 'locked_out'];
        }

        if (! Hash::check($code, $otp->code_hash)) {
            $otp->incrementAttempts();

            return ['status' => 'mismatch'];
        }

        return ['status' => 'ok'];
    }

    /**
     * Delete the active OTP for the email, if there is one.
     */
    public static function invalidate(string $email): void
    {
        $otp = OtpCode::findValidForEmail(self::normalize($email));

        if ($otp !== null) {
            $otp->invalidate();
        }
    }

    /**
     * Must match how OtpCode stores emails: trim + lowercase.
     */
    private static function normalize(string $email): string
    {
        return strtolower(trim($email));
    }
}
