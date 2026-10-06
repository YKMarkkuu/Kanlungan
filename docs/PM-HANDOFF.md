# PM Session Handoff

Last updated: 2026-10-06 (end of Phase B Commit D.2 — Phase B auth complete)

## Phase B auth status: DONE

Register, login, logout, session persistence — all working and tested in browser.

Commit chain:
- c111cc9 — feat(auth): add OTP schema and models
- f87771b — feat(auth): add OtpCodeMail and OtpService
- a7173f8 — feat(auth): add OTP controller, form requests, routes, rate limiters
- e0a447b — feat(auth): wire register page to OTP endpoints (D.1)
- 06c9970 — feat(auth): wire login page to OTP and add session auth (D.2)

## Next session starts with
- Choose ONE:
  - **Commit E: feature tests** for the full auth flow (recommended). Fix UserFactory first — still sets 'password', now stale after display_name became nullable and name became vestigial.
  - **Phase D: feed + posts** (the fun one). But built on untested auth.
- Do NOT start Phase D without deciding on Commit E.

## Roadmap status update
- Verification (OLFU Pampanga only) — DONE, satisfied by OTP registration. NOT a separate flow.
- Auth + roles — auth DONE, roles NOT started (student only for now; mod/guidance/admin/DPO roles come later)
- All other pillars NOT started.

## Known debt (do not forget)
- SECURITY (pre-production): student emails must never be written to persistent logs, exposed publicly, or returned in responses that reveal existence to unauthenticated users. Local MAIL_MAILER=log is dev-only. Production requires real SMTP. Review with IT/DPO before any real student data touches this system.
- UserFactory still sets 'password' — fix before Commit E.
- docs/AUTH.md reconciliation: locked_out is soft (row stays, resend resets attempts); MAX_ATTEMPTS=3 matches code. Full pass before Commit E.
- remember_web_... cookie observed 2026-10-06 with no matching remember_token on any user row. Believed stale from an earlier test. If it reappears, grep for Auth::login(.*true) and Auth::attempt(.*true).
- login.js duplicates register.js helpers (postJson, setBusy, showMessage, firstError). Extract if a third auth script is added.
- send-code emails unregistered addresses; endpoint doesn't know registration status and shouldn't. Rate limiter is the mitigation.
- .auth-message class still not in components.css; login.blade.php uses inline style + TODO.
- Session lifetime 43200 minutes (30 days) in .env.example. Production deployments must set their own SESSION_LIFETIME.
- name column is vestigial: registration sets it to display_name or email local part. TODO comment in OtpController. Replace or drop once user profiles ship.
- username column deferred until profile UI exists.
- Anonymous alias is per-thread, not global. Not implemented. Phase D (posts).
- Expired otp_codes rows are never garbage-collected. Add a scheduled cleanup or accept growth.
- Login page "Sign in with OLFU account" button removed in D.2. If SSO ever ships, it requires OAuth setup + IT approval. Phase G minimum.

## Phase B commit plan
- A: migrations + models — DONE (c111cc9)
- B: mail + OtpService — DONE (f87771b)
- C: controllers + routes — DONE (a7173f8)
- D.1: register page wiring — DONE (e0a447b)
- D.2: login page wiring + session — DONE (06c9970)
- E: feature tests — NEXT (recommended)
