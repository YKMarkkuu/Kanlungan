# PM Session Handoff

Last updated: 2026-10-05 (end of Phase B Commit D.1)

## Last session shipped
- e0a447b - feat(auth): wire register page to OTP endpoints
- 6 files: register.js, register.blade.php, login.blade.php, guest.blade.php, RegisterRequest, OtpController
- Tested in browser end-to-end: send code, verify ok/mismatch/locked_out, register success + redirect + flash, duplicate email, duplicate student_id, resend countdown, rate limit
- Prior: a7173f8 (Commit C), f87771b (Commit B), c111cc9 (Commit A)

## Next session starts with
- Phase B Commit D.2: wire login page to OTP endpoints
  - login.blade.php form has no submit handler
  - Question to settle first: what does "login" mean here — new OTP session, or re-auth via existing user? Does the existing /auth/verify-code suffice, or do we need a login-specific endpoint?
  - Session-based auth (Auth::login()) is the goal of D.2
- NOT Commit E (tests) in the same session

## Known debt (do not forget)
- SECURITY (pre-production): student emails must never be written to persistent logs, exposed publicly, or returned in responses that reveal existence to unauthenticated users. Local MAIL_MAILER=log is dev-only. Production requires real SMTP. Review with IT/DPO before any real student data touches this system.
- UserFactory still sets 'password' - fix before Commit E.
- docs/AUTH.md reconciliation: locked_out is soft (row stays, resend resets attempts); MAX_ATTEMPTS=3 matches code. Full pass before Commit E. Separate docs commit.
- Email regex vs client hint: register.js now matches server regex. Reconciled in D.1. (Closed.)
- register.js server-side 422 handling: email_taken reveals existence to anyone with inbox access. Accepted, noted in code. Revisit in security pass.
- .auth-message class not in components.css; login.blade.php uses inline style + TODO comment. CSS cleanup is future work.
- Login page "Sign in with OLFU account" button is a placeholder - never wire it. Deferred indefinitely.
- Login page "Student ID" field is vestigial - the OTP flow only needs email. Decide in D.2: remove or repurpose.
- name column is vestigial: registration sets it to display_name or email local part. TODO comment in OtpController. Replace or drop once user profiles ship.
- username column deferred until profile UI exists.
- Anonymous alias is per-thread, not global. Not implemented. Phase D (posts).
- Expired otp_codes rows are never garbage-collected. Add a scheduled cleanup or accept growth.

## Phase B commit plan (do not collapse)
- A: migrations + models - DONE (c111cc9)
- B: mail + OtpService - DONE (f87771b)
- C: controllers + routes - DONE (a7173f8)
- D.1: register page wiring - DONE (e0a447b)
- D.2: login page wiring - NEXT
- E: feature tests
