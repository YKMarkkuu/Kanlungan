# Kanlungan

> Verified, anonymous-optional, campus-only safe space for OLFU Pampanga students.

Kanlungan is a web app (PWA) where enrolled OLFU Pampanga students can ask for help, share experiences, vent, and support each other — anonymously or not. It replaces the open Facebook page setup with a verified, moderated, accountable community.

**Status:** Early development. Not deployed. Local dev only.

---

## Table of contents

- [What this is](#what-this-is)
- [What this is not](#what-this-is-not)
- [Stack](#stack)
- [Local setup (first time)](#local-setup-first-time)
- [Daily workflow](#daily-workflow)
- [Project structure](#project-structure)
- [Team rules](#team-rules)
- [Branching and commits](#branching-and-commits)
- [Roles and permissions](#roles-and-permissions)
- [Environment variables](#environment-variables)
- [Troubleshooting](#troubleshooting)
- [Roadmap](#roadmap)

---

## What this is

- A **verified** space — only OLFU Pampanga students can sign up and post.
- **Anonymous-optional** — you choose per post/comment whether to show your name.
- **Moderated** — reports go through a human review queue, not straight to guidance.
- **Accountable** — anonymous to peers, but the system still records the author for safety and legal reasons. Unmasking requires an admin/DPO with a logged reason.
- **Local-first** — built for the OLFU Pampanga campus community.

## What this is not

- Not an official OLFU system (until approved).
- Not truly anonymous — see "Accountable" above.
- Not a replacement for emergency services or professional help.
- Not affiliated with Facebook, Discord, or any of those platforms.

---

## Stack

| Layer | Choice |
|---|---|
| Framework | Laravel 10 (PHP 8.1+) |
| Database | MySQL (via Laragon) |
| Frontend | Blade + vanilla CSS + vanilla JS |
| Build step | None — no Tailwind, no React, no Vite usage |
| Real-time | (TBD — likely Laravel Reverb if needed) |
| Auth | Laravel Breeze (Blade), to be customized |
| Roles | Spatie Laravel Permission |
| Local dev | Laragon 6.0 on Windows + PowerShell |
| PWA | `erag/laravel-pwa` (not yet installed) |

**Important:** This project does **not** use Tailwind. Any Tailwind classes you see in default Breeze scaffolding will render as nothing. Replace them with project CSS.

---

## Local setup (first time)

Follow these in order. Don't skip steps.

### 1. Prerequisites

Install these first if you don't have them:

- [Laragon 6.0](https://laragon.org/download/) (free version is fine)
- Git for Windows
- VS Code (optional but recommended)

Open Laragon and click **Start All** so Apache and MySQL are running.

### 2. Clone the repo

Open the Laragon Terminal and run:

```powershell
cd C:\laragon\www
git clone https://github.com/YKMarkkuu/Kanlungan.git
cd Kanlungan

3. Install PHP dependencies
powershell

composer install

4. Set up the environment file

.env is gitignored, so every clone needs its own copy.
powershell

Copy-Item .env.example .env
php artisan key:generate

5. Create the database

    Open Laragon → click Database (HeidiSQL opens).

    Right-click the Laragon.MySQL connection in the left tree.

    Click Create new → Database.

    Name: kanlungan

    Charset: utf8mb4

    Collation: utf8mb4_unicode_ci

    Click OK.

6. Point .env at your database

Open .env in VS Code and confirm these values:
text

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kanlungan
DB_USERNAME=root
DB_PASSWORD=

Laragon's default MySQL user is root with an empty password. Leave DB_PASSWORD blank.
7. Run migrations
powershell

php artisan migrate

You should see four tables created: users, password_reset_tokens, failed_jobs, personal_access_tokens.
8. Serve the app
powershell

php artisan serve

Open http://127.0.0.1:8000. You should see the Laravel welcome page.

Done. You now have Kanlungan running locally.
Daily workflow

Every time you sit down to code:
powershell

cd C:\laragon\www\Kanlungan
git pull
php artisan serve

When you're done for the day:
powershell

git status
git add .
git commit -m "type(scope): short description"
git push

Never commit directly to main. Always work on a branch and open a pull request.
Project structure
text

app/
├── Http/
│   ├── Controllers/     # Request handlers
│   ├── Middleware/      # Auth, role checks, etc.
│   └── Requests/        # Form validation
├── Models/              # Eloquent models (User, Post, Report, ...)
├── Policies/            # Authorization rules per model
├── Services/            # Business logic (e.g., moderation, points)
└── Providers/           # Service container bindings

config/                  # Framework config (don't edit unless needed)

database/
├── migrations/          # Schema changes — one file per change
├── seeders/             # Seed data (roles, test users, categories)
└── factories/           # Test model factories

public/
├── css/                 # Vanilla CSS — base, components, utilities
├── js/                  # Vanilla JS — one file per feature
└── index.php            # Entry point

resources/
└── views/               # Blade templates
    ├── layouts/         # Master layouts
    ├── partials/        # Reusable view pieces
    └── ...              # Feature folders

routes/
├── web.php              # Browser routes
└── api.php              # API routes (if any)

tests/
├── Feature/             # End-to-end tests
└── Unit/                # Isolated logic tests

Rule: Feature code goes in the folder that matches its concern. Don't dump everything in Controllers/ or views/.
Team rules

    Every task is a GitHub Issue. No issue, no work. Assign yourself before starting.

    No direct push to main. All changes go through a pull request.

    One commit, one concern. Don't bundle unrelated changes into one commit.

    Pull before you start. Always git pull when you sit down.

    Commit often, push daily. Don't hoard local changes.

    Never commit .env or vendor/. They're gitignored for a reason.

    No contribution = no credit. If you didn't commit, you didn't work on it.

    Ask before restructuring. Don't move files or rename folders without flagging it first.

Branching and commits
Branch naming
Type	Pattern	Example
Feature	feature/short-name	feature/anonymous-posts
Fix	fix/short-name	fix/suspend-not-sticking
Docs	docs/short-name	docs/setup-guide
Chore	chore/short-name	chore/install-breeze
Commit message format

Use Conventional Commits:
text

type(scope): short description

Optional body explaining what and why.

Optional footer (e.g., "Closes #12")

Types: feat, fix, docs, chore, refactor, test, style, perf

Examples:
text

feat(auth): add student login with email OTP
fix(reports): make suspend action persist across sessions
docs(readme): add local setup guide for teammates
chore(deps): install spatie/laravel-permission

Pull request flow
powershell

git checkout main
git pull
git checkout -b feature/your-feature
# ... do work, commit ...
git push -u origin feature/your-feature

Then open a PR on GitHub. At least one teammate reviews before merging into main.
Roles and permissions

Roles are managed with spatie/laravel-permission. Planned roles:
Role	Can do
student	Post, comment, react, report
moderator	Review reports, hide/remove content, warn/mute
guidance	View referred case files only
admin	Manage users, roles, categories, all moderation actions
dpo	Unmask identities with logged reason, audit access

Rule: Unmasking a user requires the dpo or admin role and a required reason. Every unmask is written to an audit log.
Environment variables

.env is gitignored. When you add a new required key, also add it to .env.example (without the real value) so teammates know to set it.

Current required keys:
text

APP_NAME=Kanlungan
APP_ENV=local
APP_KEY=                       # php artisan key:generate
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kanlungan
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log                # dev only; production will use real SMTP

Never commit real credentials, API keys, or student data.
Troubleshooting
"Your app key is missing"

Run:
powershell

php artisan key:generate

"The database 'kanlungan' does not exist"

You haven't created the database in HeidiSQL. See step 5.
Changes to .env aren't taking effect

Laravel caches config. Clear it:
powershell

php artisan config:clear
php artisan cache:clear

"require(vendor/autoload.php): Failed to open stream"

You haven't run composer install yet. Do that.
Push rejected — "remote contains work that you do not have locally"

Someone else pushed. Pull first:
powershell

git pull --rebase

Then push.
code . doesn't work in PowerShell

Open VS Code manually: File → Open Folder → C:\laragon\www\Kanlungan.
I see Tailwind classes in a Blade file and the page looks broken

Tailwind is not installed. Replace those classes with project CSS. See Stack.
Roadmap

Status legend: ❌ not started · 🟡 in progress · ✅ done
Pillar	Status
Project setup (repo, Laravel, DB)	✅
Auth (login, register, password reset)	❌
Roles and permissions (student/mod/guidance/admin/dpo)	❌
Student verification (OLFU Pampanga only)	❌
Feed + categories	❌
Anonymous posting + per-thread alias	❌
Comments + reactions (like, helpful)	❌
Points ledger + badges	❌
Reports + severity tiers	❌
Moderation dashboard	❌
Unmask audit log	❌
Guidance case files	❌
Notifications	❌
PWA setup	❌
Mobile responsive pass	❌
OLFU approval + DPO review	❌
Contact

Repo owner: @YKMarkkuu

For questions, open a GitHub Issue — not a DM. Keep the paper trail.
text


**Step 4:** Save (`Ctrl+S`).

**Step 5:** Preview it in VS Code. Press `Ctrl+Shift+V` to open Markdown preview. You should see properly formatted headings, tables, and code blocks. If anything still looks broken in the preview, it'll look broken on GitHub too — fix it before committing.

**Step 6:** Commit and push.

```