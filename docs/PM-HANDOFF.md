# PM Session Handoff

Last updated: 2026-10-04 (end of Phase B Commit A)

## Last session shipped
- c111cc9 — feat(auth): add OTP schema and models for passwordless auth
- 4 files: create_otp_codes_table, add_verification_fields_to_users_table, OtpCode model, User model update
- Verified in Tinker: users columns, otp_codes columns, password nullability, users_student_id_unique index
- Verified reversible: migrate -> rollback -> migrate ran clean

## Next session starts with
- Phase B Commit B: OtpCodeMail, emails/otp-code.blade.php, OtpService
- NOT Commit C (controllers/routes) in the same session

## Known debt (do not forget)
- UserFactory still sets 'password' — stale after removal from $fillable. Fix before Commit E.
- docs/AUTH.md reconciliation: locked_out semantics + MAX_ATTEMPTS are already noted; do a full pass before Commit C ships. Separate docs commit.
- MAIL_MAILER=log needed locally for Commit B testing (not committed).
- Expired otp_codes rows are never garbage-collected. Add a scheduled cleanup or accept the growth until there's real traffic.
- OtpCodeMail passes 'email' to the views, but neither uses it. Keep it as a future hook for a "wrong email?" hint; note it so it doesn't look like dead code.

## Phase B commit plan (do not collapse)
- A: migrations + models — DONE
- B: mail + OtpService — NEXT
- C: controllers + routes
- D: frontend wiring
- E: feature tests
