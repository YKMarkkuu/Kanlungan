# Kanlungan — Auth Rules

Source of truth for authentication and verification. Read before writing
any auth controller, middleware, or view.

## Scope

Students only. Faculty and staff are excluded by design.

- Student email domain: `@student.fatima.edu.ph`
- Faculty/staff email domain: `@fatima.edu.ph` (different — do not accept)
- Pampanga campus marker: email local-part contains `pam`

## Method

OTP-only. No passwords are stored, hashed, or checked.

Login and register both use the same primitive: send a 6-digit code to the
student's Fatima email, verify it, then establish the session.

## Registration flow

1. Student enters:
   - Student ID (11 digits)
   - OLFU email (`@student.fatima.edu.ph`, must contain `pam`)
   - Campus is locked to "Pampanga" (shown, not editable)
2. Backend validates:
   - Email ends with `@student.fatima.edu.ph` (case-insensitive)
   - Email local-part contains `pam`
   - Student ID is exactly 11 digits, numeric only
   - Email is not already registered
   - Student ID is not already registered
3. Backend generates a 6-digit OTP.
4. OTP is hashed and stored with `expires_at = now() + 10 minutes`.
5. OTP is emailed to the Fatima address.
6. Student enters the OTP.
7. On match and not expired:
   - Account is created (if new) OR session established (if existing)
   - OTP record is deleted
   - Student is asked to accept community rules + privacy notice (first time only)

## Login flow

Same primitive. Student enters OLFU email, gets OTP, enters OTP, session starts.
No password field. No "remember me" password behavior.

## OTP rules

- Length: 6 digits, numeric.
- Expiry: 10 minutes.
- Max sends per email: 5 per hour.
- Max attempts per code: 3. Exceeded → invalidate code and require a new one.
- OTP is stored hashed (bcrypt or hash()), never plaintext.
- One active OTP per email at a time. New request invalidates old.
- Rate-limit by email AND by IP.

## Account rules

- One email = one account.
- One student ID = one account.
- Student ID format: 11 digits, numeric only. No validation beyond length + digits
  until registrar confirms the exact scheme.
- Display name is optional. If not set, defaults to the per-thread alias.
- Campus is always `pampanga`. Not user-editable.

## Anonymity reminder

Auth identifies the account. Anonymity is per-post/per-comment.

- The system always knows who posted (stored `author_id`).
- Peers never see `author_id`, only the per-thread alias.
- Unmasking requires `admin` or `dpo` role with a logged reason.

## Dev setup

- `MAIL_MAILER=log` in `.env`.
- OTP appears in `storage/logs/laravel.log` after each request.
- To read it:
  ```powershell
  Get-Content storage\logs\laravel.log -Tail 50
```

## Production requirements

- Real mailer (Resend, Postmark, Amazon SES, or SMTP).
- Sender address on a domain you control (e.g., `noreply@kanlungan.app`).
- HTTPS only. Session cookies `secure` and `httpOnly`.
- OTP rate-limit table cleaned up daily (queue job or scheduler).

## What NOT to do

- Do not store OLFU passwords. Ever.
- Do not allow `@fatima.edu.ph` (faculty/staff domain).
- Do not allow emails without `pam`.
- Do not auto-verify Student ID without the email OTP.
- Do not skip rate limiting — OTP endpoints are the #1 abuse target.
- Do not log the raw OTP in production. Log only in `local` env.
- Do not unmask users without a logged reason.
