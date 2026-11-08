# Kanlungan — Staff Dashboard

Dashboard for **admin** and **guidance** accounts at `/staff`. Students never see it; staff never see the student feed.

## The idea

Students need a safe space to vent and share, anonymously. The school needs to act on people who spread sexual content, profanity, harassment and threats. So:

- Students sign in with their school account. The system always knows who posted (`author_id`).
- Staff see **only posts and comments that students reported**. Never the rest of the feed.
- Staff see the author under the alias other students saw ("Anonymous Owl #1093"), not their name.
- Staff can still **trace** a reported account: **Reveal who this is** (admin and guidance only) shows name, school email and student ID. It needs a written reason (10+ characters), is saved in the audit log, and the identity stays on screen for **30 minutes**, only for the person who revealed it.
- Reporters are never named to staff. Not even on false reports.

## Setting it up

1. `php artisan migrate` (no new migrations; the schema already had roles, mod_actions and reports).
2. Create each staff account (staff cannot register themselves):

   ```powershell
   php artisan staff:create admin@fatima.edu.ph admin --name="Mr. Dela Cruz"
   php artisan staff:create counselor@fatima.edu.ph guidance --name="Ms. Reyes"
   ```
   Roles: `admin`, `guidance`, `moderator` (older, narrower role: can decide but cannot reveal identities).
3. Staff sign in at **`/staff/login`** with a 6-digit code sent to their email (same OTP system as students, no passwords). A code is only sent if the email belongs to a staff account, and the page never says whether it does.
4. Locally, with `MAIL_MAILER=log`, read the code in `storage/logs/laravel.log`.

**Placeholder accounts for local testing:** `php artisan db:seed --class=StaffDemoSeeder` creates
`mqdavid0738pam@admin.fatima.edu.ph` / `admin123` (admin) and `guidance0001pam@admin.fatima.edu.ph` / `guidance123` (guidance).
The password form on `/staff/login` and the seeder only work when `APP_ENV=local`; on any other environment the form is hidden, its route returns 404, and the seeder does nothing. Delete these accounts before going live.

Student addresses (`@student.fatima.edu.ph`) are refused by `staff:create`. To turn an existing account into staff use `php artisan community:role <email> <role>`.

## What's on each page

| Page | Purpose |
|---|---|
| Overview | One-sentence queue summary, four queues, most serious waiting cases, reasons chart (30 days), recent staff activity |
| Reports | Tabs: Needs a decision, Referred to Guidance, In community vote, Closed, All. Filter by severity and post/comment |
| Case | The reported content, why it was reported, community vote, the account's record, suggested action, decision form, reveal |
| Restricted accounts | Who is muted or banned now, listed as "Account 0042" (no names) |
| Audit log | Every decision and every reveal: who, when, why |

Times on staff pages are shown in Philippine time (`StaffDashboardService::TIMEZONE`). The database still stores UTC.

## Decisions

Every action needs a reason and is logged. Admin and guidance can: warn, remove/restore content, mute (1, 3, 7, 14 or 30 days; default 7), ban, unmute, unban, close with no action. Admin can also refer a case to Guidance; Guidance can mark referred cases handled. Ban and remove ask "are you sure?".

**Suggested action** (advice only; staff always decide), based on earlier warnings, mutes and bans on the account: first offence, warn; second, mute; third or later, ban. Self-harm reports suggest *refer to Guidance* instead of a penalty; threats suggest *remove* first.

## Works with the rest of the app

- **Safety reports** (self-harm, threats) go straight to staff and are never put to a community vote; the case page says so and suggests referring self-harm to Guidance.
- **Photos**: posts can have up to 3 photos; staff see them on the case page.
- **Wrong category**: staff can correct the report's reason while deciding (the "Reason category" field), same as the old moderation page.
- **Deleted accounts**: if the student deleted their account, the case can only be closed. It cannot be warned, restored, or revealed.
- Runs on MySQL and PostgreSQL (the full test suite passes on both).

## Changed rules (compared with before)

- `ModerationService::GUIDANCE_CAN_DECIDE = true`: guidance can decide on any case. Set it to `false` to go back to "guidance can only mark referred cases handled".
- Reveal (`ModerationService::REVEAL_ROLES`) is now `admin`, `guidance`, `dpo`. It was `admin`, `dpo`.
- Staff are redirected away from student pages (`StudentsOnly` middleware). Staff can no longer open `/feed`, `/me`, `/search` etc.
- New report reasons: **Sexual content** and **Profanity** (high severity). They appear in the student report sheet and the rules page automatically.
- `User` now casts `muted_until` and `banned_at` as dates (listed as debt in PM-HANDOFF). `$fillable` is unchanged.
- Old `/moderation` links redirect to `/staff/reports`.

## Before real students use it

- Real SMTP (the app already refuses to boot in production with the log mailer).
- Lower `SESSION_LIFETIME` for production; the example value is 30 days, which is long for an account that can reveal identities.
- IT / DPO review of who gets `admin` and `guidance`, and of revealing student IDs.

## Not built (ideas)

- Automatic bad-word filter that flags posts before anyone reports them. Today everything goes through student reports.
- Emailing guidance when a critical report arrives.
- Appeals by students.
