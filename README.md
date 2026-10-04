# Kanlungan

> Verified, anonymous-optional, campus-only safe space for OLFU Pampanga students.

Kanlungan is a web app (PWA) where enrolled OLFU Pampanga students can ask for help,
share experiences, vent, and support each other — anonymously or not. It replaces the
open Facebook page setup with a verified, moderated, accountable community.

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
- **Accountable** — anonymous to peers, but the system still records the author for
  safety and legal reasons. Unmasking requires an admin/DPO with a logged reason.
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

**Important:** This project does **not** use Tailwind. Any Tailwind classes you see
in default Breeze scaffolding will render as nothing. Replace them with project CSS.

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

Open the Laragon **Terminal** and run:

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

You should see four tables created: users, password_reset_tokens,
failed_jobs, personal_access_tokens.
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

Never commit directly to main. Always work on a branch and open a pull request. See Branching and commits.