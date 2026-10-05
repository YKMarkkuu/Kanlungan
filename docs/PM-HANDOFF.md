# PM Session Handoff

Last updated: 2026-10-05 (end of Phase B Commit C)

## Last session shipped
- f87771b - feat(auth): add OtpCodeMail and OtpService for passwordless verification
- 5 files: OtpCodeMail, OtpService, 2 email views, .env.example comment
- Tested in tinker with MAIL_MAILER=log: all four verify() branches confirmed (ok, expired_or_missing, locked_out, mismatch)
- Prior: c111cc9 - OTP schema and models; c4c8ac7, aa39698 - handoff docs

## Next session starts with
- Phase B Commit D: frontend wiring (register.js, login.blade.php), reconcile client validation with server regex

## Known debt (do not forget)
- UserFactory still sets 'password' - fix before Commit E.
- docs/AUTH.md reconciliation: locked_out is soft (row stays, resend resets attempts); MAX_ATTEMPTS=3 matches code. Full pass before Commit C ships. Separate docs commit.
- MAIL_MAILER=log is local .env only; .env.example stays at smtp.
- Expired otp_codes rows are never garbage-collected. Add a scheduled cleanup or accept the growth until there's real traffic.
- OtpCodeMail passes 'email' to the views, but neither uses it. Keep it as a future "wrong email?" hint; not dead code.
- register.js validation is looser than server regex: client checks `pam` presence, server requires `^[a-z]+[0-9]{4}pam@student\.fatima\.edu\.ph$`. Reconcile in Commit D.
- Verify endpoint does not consume OTP; register must be called with the same code. Frontend (Commit D) must handle this flow.
- PowerShell Out-File needs -Encoding ascii (not utf8) to avoid BOM breaking curl JSON bodies.
- Auto-verify at registration: verified_at = now(). The "Verification" roadmap pillar is satisfied by OTP registration, not a separate flow; README roadmap status is updated.
- `'auth/*'` is exempt from CSRF in `VerifyCsrfToken` (TODO in the file). Revisit in Commit D once sessions exist.
- `register` has no transaction. If the `users.email` / `users.student_id` unique-key race ever fires, it surfaces as a 500 in the logs; add a `try/catch` on `QueryException` then.

## Phase B commit plan (do not collapse)
- A: migrations + models - DONE (c111cc9)
- B: mail + OtpService - DONE (f87771b)
- C: controllers + routes - DONE (pending hash)
- D: frontend wiring
- E: feature tests
