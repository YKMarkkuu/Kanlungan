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

# PM Session Handoff

Last updated: 2026-10-18 (security audit closed)

## Status: Security audit complete, 42 tests passing

All findings from `docs/AUDIT.md` closed on 2026-10-18. See commit history:
- 19 commits: 75ff4e9 → 3fbc5e8
- Test suite: 42 passing (242 assertions)
- Prior: community merge cd6e085, auth phase B bab50c8

## Next session starts with
- **Rest from security work.** Next session should be visible/creative, not hardening.
- Options:
  - **Mobile responsive pass** — big pillar, not started. Fixed heights in music widgets will break at 375px.
  - **Notifications UI** — alerts exist, but the full system (in-app + PWA push) isn't built.
  - **PWA setup** — offline support, install prompt, manifest.
  - **Delight/polish** — item 3 (optimistic reactions), item 4 (modal animations).
- Do NOT start another audit unless a major merge has happened.

## Known debt (still open — see full list in docs/PM-HANDOFF.md history)
- SECURITY (pre-production): real SMTP required, no `MAIL_MAILER=log` in production. IT/DPO review needed.
- Vote on own comment as "helpful" is recorded (only points blocked). Fix in a small follow-up.
- Logout button appears twice (feed top-right + profile bottom). Decide canonical location when real nav ships.
- `User.php` fillable omits points/role/banned_at **intentionally** — do NOT add. Add missing casts for points, muted_until, banned_at.
- Guidance tab reachable by URL for moderators (Low).
- Register duplicate-key race leaks email/ID to logs (Low).
- Dead code: DemoCommunity.php, report-menu.blade.php, home.blade.php, home.js, layouts/app.blade.php, welcome.blade.php.
- Doc drift: README roadmap, AUTH.md regex rule vs code, FRONTEND.md route map.
- Hardcoded colors in community.css.
- Email regex duplicated in 4 FormRequests + 2 JS files.
- routes/web.php, login.js, register.js missing final newline.
- OG `display_name` is non-unique (allows impersonation).
- `/search` is unthrottled and uses leading-wildcard LIKE.
- Post detail loads all comments; feed capped at 50 with no pagination.
- sweepDue() writes on every feed GET; reports:resolve-due unscheduled.
- No notifications yet, no PWA, no mobile responsive.
- Guidance case files not built.

## Session history
- 2026-10-12: Community merge
- 2026-10-16: Audit run (findings documented in docs/AUDIT.md)
- 2026-10-18: All audit tiers closed, auth tests added

# PM Session Handoff

Last updated: 2026-10-22 (staff dashboard)

## Staff dashboard: DONE — see `docs/STAFF-DASHBOARD.md`
- `/staff` for admin and guidance: overview, reports queue, case page, restricted accounts, audit log. Own sign-in at `/staff/login`; accounts made with `php artisan staff:create`.
- Staff only see reported content, under aliases. Reveal (admin/guidance/dpo) needs a reason, is logged, and is shown for 30 minutes to the revealer only.
- Staff are blocked from student pages (`StudentsOnly`); students are blocked from `/staff`.
- New report reasons: Sexual content, Profanity.
- Guidance can now decide (not just mark handled): `ModerationService::GUIDANCE_CAN_DECIDE`.
- Tests: 67 passing (25 new in `StaffDashboardTest`). Existing `ModerationFlowTest` updated for the new URLs and privacy rules (staff no longer see the reporter's name).
- Closed debt: `User` casts for `muted_until` / `banned_at` / `points`. "Guidance tab reachable by URL for moderators" no longer applies (old tabs removed).
- Dead code removed: `ModerationController`, `moderation/index.blade.php`, `ModerationService::queue/history/card`.
