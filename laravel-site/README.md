# Travel With You

A Laravel 12 + Blade travel storefront and administration area styled after the existing Travel With You React interface.

## Requirements

- PHP 8.2+
- Composer 2+
- SQLite (enabled by default) or another Laravel-supported database

## Local setup

From this directory:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

The canonical prototype catalogue, package itineraries and galleries, ship cabins, offers, promo codes, guides, and FAQs are seeded idempotently into the database. Package images and the Newsreader (including italic), Hanken Grotesk, and DM Mono fonts are stored under `public/assets/` and `public/fonts/`; the site does not require a frontend build step.

To fetch any missing self-hosted prototype photographs, run `scripts/fetch-assets.ps1` on Windows or `scripts/fetch-assets.sh` on Unix-like systems. The source is documented in `public/assets/CREDITS.md`.

Admin media uploads accept images and videos up to 500 MB. Configure PHP with `upload_max_filesize=500M` and `post_max_size=510M` (plus a request timeout long enough for large files), and ensure PHP's temporary upload directory and `public/assets/uploads` are writable. Restart the web server after changing PHP or server limits.

The configured tax rate is `TRAVEL_TAX_RATE` in `.env` (defaults to `0.07`); promo-code stacking on package deals is controlled by `TRAVEL_STACK_PROMO_ON_DEALS` (defaults to `true`).

Cruise packages are linked to their prototype fleet ships, and guests can save journeys before signing in; saved guest journeys are added to the account when the guest logs in or registers.

## Administrator account

Set `ADMIN_NAME`, `ADMIN_EMAIL`, and a strong `ADMIN_PASSWORD` in `.env`, then run `php artisan db:seed`. The seeder creates or updates that administrator account. Admin access is at `/admin/login`. On first sign-in, register the displayed Base32 secret with an authenticator app and verify its 6-digit code. Public registration cannot grant administrator privileges.

Do not commit `.env` or production credentials. Configure a production database, mail transport, `APP_DEBUG=false`, HTTPS, backups, and your chosen payment provider before accepting paid reservations. Checkout currently records a pending booking request; it does not collect payment.

## Sending email

To deliver enquiry replies, configure `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` in `.env`. Do not commit mail credentials. With the default `log` mail driver, messages are written to `storage/logs` instead of being delivered to customers.

## Validation

```powershell
php artisan test
php artisan route:list
```

The site uses responsive CSS breakpoints for phone, tablet, laptop, and desktop widths. It is self-contained in this directory and does not depend on the former React/Vite prototype.
