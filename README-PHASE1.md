# ধাপ ১ — Setup, Authentication, Roles

This folder is an **overlay** for a fresh Laravel 12 project. Copy it over the new project and
overwrite when asked.

## Install

```bash
composer create-project laravel/laravel somiti "12.*"
cd somiti
cp -r /path/to/somiti-phase1/* .            # overwrite existing files

npm install
npm install alpinejs @alpinejs/focus
```

**1. Autoload the Bengali helpers** — in `composer.json`, add `files` under `autoload`:

```json
"autoload": {
    "psr-4": { "App\\": "app/", "Database\\Factories\\": "database/factories/", "Database\\Seeders\\": "database/seeders/" },
    "files": ["app/helpers.php"]
}
```
then run `composer dump-autoload`.

**2. Timezone** — in `config/app.php` set `'timezone' => 'Asia/Dhaka',`.

**3. `.env`**

```env
APP_NAME="Somiti"
APP_LOCALE=bn
APP_FALLBACK_LOCALE=en
APP_FAKER_LOCALE=en_US

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=somiti
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
CACHE_STORE=database

ADMIN_USERNAME=admin
ADMIN_NAME="এডমিন"
ADMIN_PASSWORD="choose-a-strong-password"
```

Create the database with `CREATE DATABASE somiti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;`
then:

```bash
php artisan migrate --seed
npm run build                  # or: npm run dev
php artisan serve
php artisan test
```

With `APP_ENV=local` the seeder also creates two demo collectors: `rahim` / `password` and `karim` / `password`.

## What Phase 1 delivers

| Area | Files |
|---|---|
| Username login, remember me, 5-try throttle, inactive-user lockout | `Auth/LoginController`, `LoginRequest`, `EnsureUserIsActive` |
| Roles (admin / collector), route guards | `Enums/UserRole`, `EnsureRole`, `routes/web.php`, `bootstrap/app.php` |
| Settings: org info, **share value (৳20 default)**, weekly off-days, receipt footer | `SettingService`, `SettingController`, `admin/settings/edit` |
| Admin-maintained holidays | `Holiday`, `HolidayController`, `CalendarService` |
| Race-safe reference numbers (for receipts, handovers, accounts) | `NumberSequenceService` |
| Append-only audit log (login, logout, settings, holidays) | `AuditLog`, `AuditService` |
| Bengali display helpers: digits, ৳১,২০,০০০ grouping, dates, amount in words | `Support/BanglaFormatter`, `helpers.php`, `resources/js/app.js` |
| Bengali UI: layouts, responsive sidebar for both roles, dashboards, error pages | `resources/views/**`, `lang/bn/*` |
| Tests | `tests/Feature/**`, `tests/Unit/BanglaFormatterTest.php` |

Sidebar items for later phases are shown greyed out with their phase number.
Business rules agreed so far: `docs/business-rules.md`.
