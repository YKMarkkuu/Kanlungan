<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class OtpCode extends Model
{
    // Matches docs/AUTH.md — do not change without updating that doc
    public const MAX_ATTEMPTS = 3;

    public const TTL_MINUTES = 10;

    public const CODE_LENGTH = 6;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'attempts' => 'integer',
    ];

    /**
     * Invalidate any existing OTP for the email, then create a fresh one.
     *
     * Returns the new row and the plaintext code. The plaintext exists only
     * in the return value, for the caller to mail; it is never persisted.
     * The email is stored exactly as given, so callers must normalize it
     * (trim + lowercase) consistently before calling any method here.
     *
     * @return array{otp: self, plain: string}
     */
    public static function generateForEmail(string $email): array
    {
        $plain = str_pad(
            (string) random_int(0, (10 ** self::CODE_LENGTH) - 1),
            self::CODE_LENGTH,
            '0',
            STR_PAD_LEFT
        );

        // Hash before opening the transaction so bcrypt doesn't run while it's held open.
        $hash = Hash::make($plain);

        $otp = DB::transaction(function () use ($email, $hash) {
            static::where('email', $email)->delete();

            return static::create([
                'email' => $email,
                'code_hash' => $hash,
                'attempts' => 0,
                'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            ]);
        });

        return ['otp' => $otp, 'plain' => $plain];
    }

    /**
     * Return the active, non-expired row for the email, or null.
     *
     * Checks expiry only. Attempt limits are deliberately NOT filtered here so
     * the verify logic can tell "locked out" apart from "expired / missing".
     */
    public static function findValidForEmail(string $email): ?self
    {
        return static::where('email', $email)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    /**
     * Atomically increment the attempt counter (single UPDATE ... SET attempts = attempts + 1).
     */
    public function incrementAttempts(): void
    {
        $this->increment('attempts');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Invalidate this OTP by deleting the row.
     */
    public function invalidate(): void
    {
        $this->delete();
    }
}
