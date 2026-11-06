<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ModerationService;
use App\Services\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * Staff sign-in: an emailed 6-digit code, same primitive as the student login (docs/AUTH.md), but only for
 * accounts that already exist with a staff role. Staff accounts are created with `php artisan staff:create`;
 * nobody can register themselves as staff.
 *
 * The page never says whether an email belongs to staff, and no code is sent to other addresses, so this
 * cannot be used to probe accounts or to spam inboxes.
 */
class AuthController extends Controller
{
    public function show()
    {
        return view('staff.login', ['email' => session('staff_email'), 'devLogin' => $this->devLoginEnabled()]);
    }

    public function sendCode(Request $request): RedirectResponse
    {
        $email = $this->email($request);

        if ($this->staffAccount($email) !== null) {
            OtpService::send($email);
        }

        return redirect()->route('staff.login')
            ->with('staff_email', $email)
            ->with('status', 'If that is a staff account, a 6-digit code is on its way. It works for 10 minutes.');
    }

    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'digits:6']], ['code.digits' => 'Enter the 6-digit code.']);
        $email = $this->email($request);
        $user = $this->staffAccount($email);

        $result = OtpService::verify($email, $data['code']);

        // An unknown or non-staff email never has a code, so it lands in "expired_or_missing" like a stale one.
        if ($user === null || $result['status'] !== 'ok') {
            $message = match ($result['status']) {
                'mismatch' => 'That code is not right. Check the email and try again.',
                'locked_out' => 'Too many wrong tries. Ask for a new code.',
                default => 'That code has expired or was never sent. Ask for a new code.',
            };

            return redirect()->route('staff.login')->with('staff_email', $email)->withErrors(['code' => $message]);
        }

        Auth::login($user, false);
        $request->session()->regenerate();
        OtpService::invalidate($email);

        return redirect()->route('staff.dashboard');
    }

    /**
     * Local-development shortcut: email + password, for the placeholder accounts from StaffDemoSeeder.
     * Answers 404 anywhere except APP_ENV=local, so it does not exist on a real server.
     */
    public function devLogin(Request $request): RedirectResponse
    {
        abort_unless($this->devLoginEnabled(), 404);

        $data = $request->validate(['email' => ['required', 'string', 'email'], 'password' => ['required', 'string']]);
        $user = $this->staffAccount(strtolower(trim($data['email'])));

        if ($user === null || $user->password === null || ! Hash::check($data['password'], $user->password)) {
            return redirect()->route('staff.login')->withErrors(['email' => 'Wrong email or password.'])->withInput(['email' => $data['email']]);
        }

        Auth::login($user, false);
        $request->session()->regenerate();

        return redirect()->route('staff.dashboard');
    }

    private function devLoginEnabled(): bool
    {
        return app()->environment('local');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('staff.login')->with('status', 'You have been signed out.');
    }

    private function email(Request $request): string
    {
        return strtolower(trim((string) $request->validate(['email' => ['required', 'string', 'email', 'max:190']])['email']));
    }

    private function staffAccount(string $email): ?User
    {
        return User::where('email', $email)->whereIn('role', ModerationService::STAFF)->first();
    }
}
