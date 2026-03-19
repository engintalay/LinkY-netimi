# Copilot Instructions – LinkY (Link Yöneticisi)

## Running the App

```bash
php -S localhost:8080
```

No build tools, no package manager. PHP 7.4+ required with extensions: `php-sqlite3`, `php-curl`, `php-mbstring`, `php-xml`.

Default credentials: `admin` / `admin`.

After schema changes, run `update_db.php` once via browser, then delete it.

## Architecture

Page-based PHP app with no framework, no routing library, no ORM.

```
index.php             → redirect only (login ↔ dashboard)
login.php             → auth + brute-force protection
logout.php            → session destroy
dashboard.php         → link display (grid/list, search, category filter)
manage.php            → CRUD for links and categories (action= param)
ajax_fetch_title.php  → AJAX endpoint: fetches title + images from a URL
update_db.php         → one-time migration utility (delete after use)
includes/db.php       → PDO connection, schema creation, auto-migration
includes/functions.php→ all shared functions
admin/users.php       → user management (admin only)
admin/user_permissions.php → category access per user (admin only)
admin/login_logs.php  → login audit log (admin only)
assets/style.css      → all styles (glassmorphism design, CSS variables)
database.sqlite       → SQLite database file (auto-created)
```

## Database Access

Raw SQL via PDO with prepared statements only — no ORM, no query builder.

```php
$stmt = $pdo->prepare("SELECT * FROM links WHERE id = ?");
$stmt->execute([$id]);
$link = $stmt->fetch(); // returns associative array
```

`$pdo` is initialized in `includes/db.php` and available globally after including it. Tables are auto-created on first run; columns are added via `update_db.php` or the auto-migration in `db.php`.

## Key Tables

| Table | Purpose |
|-------|---------|
| `users` | Auth, roles (`admin`/`user`), lockout state |
| `categories` | Link categories, owned by a user |
| `links` | Bookmarks: url, title, description, image_url, category_id |
| `user_category_permissions` | Which categories each non-admin user can see |
| `blocked_ips` | IPs permanently blocked after repeated lockouts |
| `login_logs` | Full audit trail of all login attempts |

Non-admin users only see categories they've been granted permission to in `user_category_permissions`. Admins see everything.

## Authentication & Authorization

- Sessions: `$_SESSION['user_id']`, `$_SESSION['username']`, `$_SESSION['role']`
- Call `requireLogin()` at the top of every protected page
- Use `isAdmin()` to gate admin-only logic
- `session_start()` is called inside `includes/functions.php`

Brute-force rules (all in `login.php`):
- 3 failed attempts → 10-minute lockout
- 3 lockouts/day from same IP → IP blocked
- 6 lockouts/day → permanent account lock

## Conventions

**File structure per page:** PHP logic block at top, then full HTML document with embedded `<?php ?>` tags. No separate template files.

**Output escaping:** Always use `htmlspecialchars()` or the `sanitize()` helper on user-supplied data before rendering. Echo shorthand `<?= ?>` is used throughout.

**CRUD routing:** `manage.php` uses `?action=` to dispatch operations:
- `new_link`, `edit_link`, `delete_link`
- `new_category`, `edit_category`, `delete_category`

**AJAX:** `ajax_fetch_title.php` returns JSON `{title, images[], error}`. Called from `manage.php` via the "Çek" (Fetch) button.

**CSS:** Glassmorphism style using CSS variables defined in `assets/style.css`. Key classes: `.glass-card`, `.link-grid`, `.btn`, `.tag`, `.alert`, `.form-group`. Dual views (grid/list) toggled by adding `.view-list` to `.link-grid`.

**Naming:** camelCase for PHP functions (`fetchUrlDetails`), snake_case for DB columns (`user_id`, `created_at`).

**No CSRF tokens** are implemented — keep this in mind when adding form-based actions.
