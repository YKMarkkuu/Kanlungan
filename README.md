# Kanlungan

Kanlungan is a verified, campus-only safe space for OLFU Pampanga students. Students can participate under their display name or use per-thread anonymous aliases, within a community designed for support, accountability, and safety.

Kanlungan is a web application and installable Progressive Web App (PWA). Access is intended for eligible OLFU Pampanga students; Kanlungan is not an official university system unless and until the university formally approves it.

## Core Features

- **OTP authentication:** Passwordless sign-in using a one-time code sent to an eligible OLFU student email address.
- **HTMX navigation:** Page navigation swaps server-rendered Blade content without a full-page reload, with standard navigation available as a fallback.
- **Anonymous aliases:** Students can choose anonymity for posts and comments. The application retains account attribution for moderation and accountability.
- **Community moderation:** Report workflows, moderator review, content actions, and restricted access to identity unmasking.
- **Privacy and DPA-aware safeguards:** Student-only access, limited disclosure of identity, and logged unmasking actions support responsible handling of personal information. This description is not a certification of compliance; production use should be reviewed with the university and its Data Protection Officer (DPO).

## Technology Stack

| Area | Technology |
| --- | --- |
| Application framework | Laravel 10 |
| Server-rendered UI | Blade templates |
| Navigation and interactions | HTMX and vanilla JavaScript |
| Styling | Vanilla CSS |
| Production database | PostgreSQL, hosted through Supabase |
| Local development database | MySQL |
| Transactional email | Brevo API |
| Hosting | Render |
| Web application format | Progressive Web App (PWA) |

## Local Setup

The following instructions use PowerShell and a local MySQL database. The production environment uses PostgreSQL; set its connection variables in the deployment environment rather than using the local MySQL values below.

### Prerequisites

- PHP 8.1 or later with the extensions required by Laravel and your selected database driver
- Composer
- Git
- MySQL for local development (Laragon includes MySQL and HeidiSQL)

### 1. Clone the repository

```powershell
cd C:\laragon\www
git clone https://github.com/YKMarkkuu/Kanlungan.git
Set-Location .\Kanlungan
```

### 2. Install PHP dependencies

```powershell
composer install
```

### 3. Create and configure the environment file

Create a local `.env` file and generate an application key:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

Edit `.env` and set the local URL and MySQL connection. Use `MAIL_MAILER=log` only for local development; production must use the configured Brevo mail transport and credentials.

```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=kanlungan
DB_USERNAME=root
DB_PASSWORD=

MAIL_MAILER=log
```

Never commit `.env`, API keys, database passwords, OTPs, or real student data.

### 4. Create the local MySQL database

With Laragon's MySQL running and its client available in PowerShell, create the database with:

```powershell
mysql -u root -e "CREATE DATABASE kanlungan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
```

Alternatively, create a database named `kanlungan` with the `utf8mb4` character set and `utf8mb4_unicode_ci` collation in HeidiSQL. If your local MySQL account has a password, update `DB_PASSWORD` in `.env`.

### 5. Run database migrations

```powershell
php artisan migrate
```

### 6. Start the development server

```powershell
php artisan serve
```

Open [http://127.0.0.1:8000](http://127.0.0.1:8000) in your browser.

### Optional: run the test suite

Configure a test database in `phpunit.xml` or your test environment before running:

```powershell
php artisan test
```

## Architecture and Project Structure

Kanlungan follows Laravel's MVC conventions. Controllers handle HTTP requests, Eloquent models represent persisted data, and Blade templates render HTML on the server. HTMX enhances navigation and selected interactions by exchanging HTML fragments; the core UI uses vanilla CSS and JavaScript rather than a client-side framework.

| Directory | Responsibility |
| --- | --- |
| `app/` | Application code, including models, HTTP controllers and middleware, services, and providers. |
| `resources/views/` | Blade layouts, pages, and reusable view components. The community layout configures HTMX navigation and fragment swapping. |
| `public/` | Public entry point and static assets, including CSS, JavaScript, images, and the PWA service worker. |
| `routes/` | Web and API route definitions. |
| `database/` | Schema migrations, seeders, and model factories. |
| `tests/` | Feature and unit tests. |
| `config/` | Laravel and application configuration. |

## Roles and Permissions

Role checks are applied to protected routes and actions. Actual access depends on the configured role and the relevant route authorization.

| Role | Typical access |
| --- | --- |
| **Student** | Create posts and comments, react, report content, and use community features. |
| **Moderator** | Review reports and take moderation actions. |
| **Guidance** | Access the moderation queue and moderation actions available to that role. A guidance case-file system is not currently part of the application. |
| **Admin** | Perform administrative moderation actions and access identity unmasking where authorized. |
| **DPO** | Access identity unmasking for privacy oversight. Unmasking requires a reason and is audited. |

Anonymous aliases hide identity from other community members; they do not make activity anonymous to the application. Identity unmasking must be limited to authorized personnel and used only for a documented reason.

## Production and Deployment Notes

The deployed application is hosted on Render and uses PostgreSQL through Supabase and Brevo for transactional email. Configure environment variables and credentials in the hosting provider's secret/environment settings; do not store production secrets in the repository.

For production deployments:

- Set `APP_ENV=production`, `APP_DEBUG=false`, and the correct HTTPS `APP_URL`.
- Configure PostgreSQL connection details and any required SSL settings for the Supabase database.
- Configure Brevo mail delivery and verify the sender/domain before sending OTP messages.
- Run reviewed database migrations as part of the release process.
- Use durable object storage for uploaded files where required by the deployment.
- Review privacy notices, retention, access controls, incident response, and operational procedures with the university and DPO before processing real student data.

## Troubleshooting

### `No application encryption key has been specified`

Generate a local key, then restart the development server:

```powershell
php artisan key:generate
```

Do not replace an existing production `APP_KEY` casually; changing it can invalidate encrypted application data and sessions.

### Changes to `.env` are not taking effect

Clear cached configuration and restart the app:

```powershell
php artisan config:clear
php artisan cache:clear
```

For a local development environment, avoid running `php artisan config:cache` while actively changing `.env`.

### Database connection or “unknown database” errors

- Confirm MySQL is running locally and the database exists.
- Check `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`.
- Clear cached configuration after changing connection values.
- Confirm PHP has the PDO driver for the selected database (`pdo_mysql` locally or `pdo_pgsql` for PostgreSQL).

### Uploaded files return 404

Create Laravel's public storage symlink:

```powershell
php artisan storage:link
```

Also confirm the configured filesystem disk is available and that the web process has the required storage permissions.

### `vendor/autoload.php` is missing

Install the Composer dependencies from the repository root:

```powershell
composer install
```

## Security and Privacy

Please do not report vulnerabilities or submit real student information in public issues. Contact the repository maintainers privately for security concerns. Never commit secrets, raw OTPs, personal data, or production database exports.
