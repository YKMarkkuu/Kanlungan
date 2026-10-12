# PM Session Handoff

Last updated: 2026-10-12 (community features merged)

## Phase B auth status: DONE

Register, login, logout, session persistence — all working and tested in browser.

Commit chain:
- c111cc9 — feat(auth): add OTP schema and models
- f87771b — feat(auth): add OtpCodeMail and OtpService
- a7173f8 — feat(auth): add OTP controller, form requests, routes, rate limiters
- e0a447b — feat(auth): wire register page to OTP endpoints (D.1)
- 06c9970 — feat(auth): wire login page to OTP and add session auth (D.2)

## Phase D — Community Features (MERGED 2026-10-12)

Merged from Mico Rein David's branch `feature/community` as commit `cd6e085` (merge commit) / `4513932` (feature commit).

Scope: 73 files, 4491 insertions. Includes:
- Posts, comments, reactions (like, helpful)
- Reports with severity tiers, report voting, moderation decisions
- Moderation dashboard, alerts, muting/banning
- Profile pages (posts, comments, display)
- Points system and point logs
- Image upload + re-encoding
- Search
- Static pages (help, privacy, rules)
- Role middleware (RequireRole, EnforceRestrictions)
- Role assignment command (SetCommunityRole)
- 29 tests: CommunityFlowTest (8), ModerationFlowTest (16), VoteTallyTest (5)

Status: 31 tests passing (166 assertions). Manually verified in browser: auth intact, feed renders, posts/comments/reactions/reports work, logout works, auth guards still work.

## Roadmap status update
- Feed + categories — DONE
- Anonymous posting + per-thread alias — DONE (via app/Support/Alias.php)
- Comments + reactions — DONE
- Reports + severity tiers — DONE
- Moderation dashboard — DONE
- Points ledger + badges — Points DONE, badges NOT done
- Notifications — Alerts DONE, in-app notifications partial
- Profile pages — DONE
- Roles/permissions (student/mod/guidance/admin/dpo) — Middleware and role command DONE, but enforcement audit needed
- PWA setup — NOT started
- Mobile responsive — NOT started
- Guidance case files — NOT started
- Unmask audit log — PARTIAL (ModAction exists, audit trail for unmasking not verified)

## Known debt (do not forget)
- SECURITY (pre-production): student emails must never be written to persistent logs, exposed publicly, or returned in responses that reveal existence to unauthenticated users. Local MAIL_MAILER=log is dev-only. Production requires real SMTP. Review with IT/DPO before any real student data touches this system.
- docs/AUTH.md reconciliation: locked_out is soft (row stays, resend resets attempts); MAX_ATTEMPTS=3 matches code. Full pass before Commit E.
- remember_web_... cookie observed 2026-10-06 with no matching remember_token on any user row. Believed stale from an earlier test. If it reappears, grep for Auth::login(.*true) and Auth::attempt(.*true).
- login.js duplicates register.js helpers (postJson, setBusy, showMessage, firstError). Extract if a third auth script is added.
- send-code emails unregistered addresses; endpoint doesn't know registration status and shouldn't. Rate limiter is the mitigation.
- .auth-message class still not in components.css; login.blade.php uses inline style + TODO.
- Session lifetime 43200 minutes (30 days) in .env.example. Production deployments must set their own SESSION_LIFETIME.
- name column is vestigial: registration sets it to display_name or email local part. TODO comment in OtpController. Replace or drop once user profiles ship.
- username column deferred until profile UI exists.
- Expired otp_codes rows are never garbage-collected. Add a scheduled cleanup or accept growth.
- Login page "Sign in with OLFU account" button removed in D.2. If SSO ever ships, it requires OAuth setup + IT approval. Phase G minimum.
- Vote on own comment as "helpful" is not blocked. Should be, like the post-vote rule. Mico's tests confirm post-vote self-block exists but not comment-helpful self-block. Fix in follow-up commit.
- Logout button appears twice: feed top-right and profile bottom. Decide canonical location when the real nav ships.
- User.php not updated with new fillable fields. `points` and moderation columns exist in schema but are not mass-assignable. Fix before any code path uses User::create() or $user->update() with those fields.
- routes/web.php ends without a newline (cosmetic).
- Feature branch was pushed as one 73-file commit "Add community features and moderation." Bisecting this commit will be painful. For future contributions: split into logical commits.
- .sql backups should be gitignored. Add *.sql to .gitignore.

## Next session starts with
- Two options, pick ONE:
  - **Fix the debt list:** self-vote on own comment, User.php fillable, logout button placement. Small commits, testable.
  - **Commit E (auth feature tests):** still not done. Auth flow has zero PHPUnit coverage.
- Do NOT start Phase E (PWA, notifications polish, guidance case files) until debt is addressed.

## Phase B commit plan
- A: migrations + models — DONE (c111cc9)
- B: mail + OtpService — DONE (f87771b)
- C: controllers + routes — DONE (a7173f8)
- D.1: register page wiring — DONE (e0a447b)
- D.2: login page wiring + session — DONE (06c9970)
- E: feature tests — NEXT (recommended)
