# MediPulse HMS

A hospital management system built with PHP, MySQL (PDO), and vanilla JS —
no frameworks, no build step. Suitable for local XAMPP hosting.

## Quick start

1. Start XAMPP Apache and MySQL.
2. Import `database/schema.sql` into MySQL (this creates the database,
   tables, a working admin account, and a few sample records).
3. Copy this folder into your XAMPP `htdocs` directory (any folder name
   works — the app now detects its own URL automatically).
4. Open it in a browser, e.g. `http://localhost/MediPluse HMS/`.
5. Log in with:
   - **Username:** `admin` (or email `admin@medipulse.local`)
   - **Password:** `admin123`

Change this password after your first login (see "Changing the admin
password" below) — the schema ships with a known default for setup
convenience only.

## Project structure

```
MediPluse HMS/
├── config.php              App config, DB connection, auth guard, CSRF, helpers
├── index.php                Legacy entry point → redirects to /auth/login.php
├── auth/
│   ├── login.php            Login form + handler
│   └── logout.php           Destroys the session
├── dashboard/
│   └── index.php            Stats overview + upcoming appointments
├── patients/
│   ├── index.php            List, search, add, delete
│   └── edit.php             Edit an existing patient
├── doctors/
│   ├── index.php            List, search, add, delete
│   └── edit.php             Edit an existing doctor
├── appointments/
│   ├── index.php            List, filter by status, add, delete
│   └── edit.php             Edit an existing appointment
├── partials/
│   ├── head.php             Shared <head> (title, styles, favicon)
│   ├── sidebar.php          Shared nav, driven by the url() helper
│   └── scripts.php          Shared JS (mobile nav, alert auto-dismiss)
├── assets/css/style.css     Design system (colors, layout, components)
└── database/schema.sql      Tables + seed data
```

## What changed from the original scaffold

This started as a bare skeleton with a few structural issues. Here's
what was fixed, in case it's useful for your project write-up:

- **Hard-coded base path removed.** `APP_BASE_URL` used to be a literal
  string (`/MediPluse%20HMS`). It's now computed automatically from
  where `config.php` sits relative to the web server's document root,
  so renaming or moving the project folder no longer breaks every link.
- **Broken navigation fixed.** The dashboard linked to one set of files
  (`dashboard/index.php`, `auth/logout.php`) while the other pages
  linked to a different set (`../dashboard.php`, `../logout.php`).
  Every page now goes through the same `partials/sidebar.php` and the
  same `url()` helper, so there's a single source of truth for links.
- **Login couldn't actually work.** The schema never inserted the admin
  account the README told you to log in with. `database/schema.sql` now
  seeds a working admin user with a properly hashed password.
- **CSRF protection added.** Every form now submits a per-session token
  that's verified server-side before any database write.
- **Edit was missing.** Patients, doctors, and appointments could only
  be added or deleted. Each module now has a matching `edit.php`.
- **Design system rebuilt.** Consistent spacing, color tokens, icons,
  status colors per appointment state, a responsive sidebar with a
  mobile toggle, and hover/empty states throughout.
- **Search and filtering added** on patients, doctors, and appointments.

## Changing the admin password

Since there's no registration screen (by design — this is an
admin-only internal tool), generate a new hash and update the row
directly:

```php
<?php
echo password_hash('yourNewPassword', PASSWORD_DEFAULT);
```

Then update it in MySQL:

```sql
UPDATE tblusers SET passwordHash = 'PASTE_THE_HASH_HERE' WHERE username = 'admin';
```

## Notes for production use

This is set up for local/BSc-project use. Before deploying anywhere
public, you'd want to also: move `DB_USER`/`DB_PASS` out of `config.php`
into environment variables, force HTTPS, add rate-limiting on login,
and add proper input validation/length limits beyond what's here.
