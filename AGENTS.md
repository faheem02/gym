# AGENTS.md

## Project Overview

Gym Management System — a plain PHP + MySQL web app served via XAMPP. No framework, no Composer, no build step. Each page is a standalone PHP file that includes `config.php` (DB connection + session) and `includes/header.php` / `includes/footer.php`.

## Stack

- **PHP 8+** (vanilla, no framework)
- **MySQL** (database: `gym_db`, user: `root`, no password)
- **Bootstrap 5.3.3** (CDN)
- **Font Awesome 6.5.1** (CDN)
- **XAMPP** (Apache + MySQL)
- No package manager, no build tools, no test framework

## Setup

1. Start Apache + MySQL via XAMPP Control Panel
2. Import `database.sql` into MySQL to create `gym_db` with seed data
3. Run any files in `migrations/` that haven't been applied yet
4. Access at `http://localhost/gym/login.php`
5. Default credentials: `admin` / `admin123` (stored as plaintext — see Security section)

## Directory Structure

- `/` — root pages: `index.php` (dashboard), `login.php`, `logout.php`, `auth.php`, `config.php`
- `members/` — CRUD + `payments.php` + `ledger.php`
- `subscriptions/` — CRUD + `renew.php`
- `attendance/` — check-in listing
- `trainers/` — CRUD + `members.php` (assigned members)
- `staff/` — CRUD
- `plans/` — CRUD for membership plans
- `day_passes/` — add + listing
- `canteen/` — sub-modules: `pos/`, `products/`, `purchases/`, `stock/`, `suppliers/` (suppliers has `payments.php` and `ledger.php`)
- `expenses/` — CRUD + `categories/` + `ledger.php`
- `cashbook/` — single `index.php`
- `bankbook/` — single `index.php`
- `includes/` — `header.php` (sidebar nav, Bootstrap include), `footer.php`
- `assets/` — `style.css` only
- `migrations/` — `.sql` migration files (not auto-run)

## Conventions

- **Auth guard**: Every non-login page includes `includes/header.php`, which calls `config.php` (starts session) and `auth.php` (redirects to login if no session). Never skip this chain.
- **Page pattern**: Each page sets `$activePage` and `$pageTitle` before including `header.php`. The sidebar active state depends on `$activePage` matching expected values.
- **DB access**: Direct PDO via `$pdo` global (configured in `config.php`). No ORM, no query builder. Prepared statements used for user input.
- **URLs**: All internal links are absolute from webroot, e.g. `/gym/members/`, `/gym/subscriptions/add.php`. If relocating the app, these paths break.
- **Migrations**: Manual — run `.sql` files from `migrations/` by hand. They are not applied automatically. Check which have been run before modifying schema.
- **No JS build**: All JS is inline in `footer.php` or within page files. No bundler, no transpilation.
- **CSS**: Single file `assets/style.css`. Custom classes prefixed contextually (e.g. `stat-card`, `badge-active`, `login-card`).

## Important Gotchas

- **Plaintext passwords**: `login.php` compares password directly (`===`). No hashing. Default admin is `admin`/`admin123`.
- **No CSRF protection**: Forms POST without tokens.
- **XSS**: Output is escaped with `htmlspecialchars()` in most places, but verify any new output.
- **Dashboard side effects**: `index.php` runs `UPDATE subscriptions SET status = 'expired' ...` on every page load — this is intentional subscription expiry automation.
- **`session_start()` is called twice**: `config.php` calls it, and `auth.php` checks session status before calling it again. This is safe but redundant.
- **Hardcoded `/gym/` prefix**: All asset paths, links, and redirects use `/gym/`. The app assumes it lives at `/gym/` on the web server.

## Schema Notes

Core tables: `users`, `members`, `plans`, `subscriptions`, `attendance`, `trainers`, `staff`, `day_passes`

Financial tables: `member_payments`, `expenses`, `expense_categories`

Canteen subsystem: `canteen_products`, `canteen_suppliers`, `canteen_purchases`, `canteen_purchase_items`, `canteen_sales`, `canteen_sale_items`, `canteen_supplier_payments`, `canteen_stock_log`

Key relationships:
- `members.trainer_id` → `trainers.id` (ON DELETE SET NULL)
- `subscriptions.member_id` → `members.id` (ON DELETE CASCADE)
- `subscriptions.plan_id` → `plans.id`
- `attendance.member_id` → `members.id` (ON DELETE CASCADE)

## Adding New Features

1. Create directory under project root (e.g. `reports/`)
2. Add `index.php` with the standard page boilerplate: set `$activePage`/`$pageTitle`, include header, add content, include footer
3. Add nav link in `includes/header.php` under appropriate section
4. If new table needed: create migration `.sql` in `migrations/` (do not modify `database.sql` after initial setup)
5. Follow existing CRUD pattern: `index.php` (list), `add.php`, `edit.php`, `delete.php`
