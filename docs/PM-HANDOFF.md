# PM Session Handoff

Last updated: 2026-10-05 (end of Phase B Commit B)

## Last session shipped
- f87771b - feat(auth): add OtpCodeMail and OtpService for passwordless verification
- 5 files: OtpCodeMail, OtpService, 2 email views, .env.example comment
- Tested in tinker with MAIL_MAILER=log: all four verify() branches confirmed (ok, expired_or_missing, locked_out, mismatch)
- Prior: c111cc9 - OTP schema and models; c4c8ac7, aa39698 - handoff docs

## Next session starts with
- Phase B Commit C: OtpController + routes (POST /auth/send-code, POST /auth/verify-code, POST /auth/register)
- NOT Commit D (frontend wiring) in the same session

## Known debt (do not forget)
- UserFactory still sets 'password' - fix before Commit E.
- docs/AUTH.md reconciliation: locked_out is soft (row stays, resend resets attempts); MAX_ATTEMPTS=3 matches code. Full pass before Commit C ships. Separate docs commit.
- MAIL_MAILER=log is local .env only; .env.example stays at smtp.
- Expired otp_codes rows are never garbage-collected. Add a scheduled cleanup or accept the growth until there's real traffic.
- OtpCodeMail passes 'email' to the views, but neither uses it. Keep it as a future "wrong email?" hint; not dead code.

## Phase B commit plan (do not collapse)
- A: migrations + models - DONE (c111cc9)
- B: mail + OtpService - DONE (f87771b)
- C: controllers + routes - NEXT
- D: frontend wiring
- E: feature tests
