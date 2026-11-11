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

Package itinerary PDFs can be uploaded from the package editor, up to 50 MB each. They are stored on Laravel's private local disk and served through the published package PDF route for preview and download. Apply the PHP upload settings above to the active web-server PHP runtime; the CLI runtime may use different limits.

Booking requires one JPG, JPEG, or PNG passport/ID image (up to 5 MB) per traveller. Enter traveller dates as `DD/MM/YYYY` or choose them from the calendar. Quick-added cart packages must be completed in the booking wizard before payment; dietary, medical, and accessibility requests are collected there. Images are kept on the private local disk in `storage/app/private/Clients/<Region>/<Country>/<Package-Slug>_<YYYY-MM-DD>/<Booking-Reference>/`, with random filenames. Administrators can download them from the protected booking detail page. Staged cart uploads are cleaned after 30 days; booked documents are deleted 90 days after departure. Run Laravel's scheduler continuously (or invoke `php artisan schedule:run` from Windows Task Scheduler every minute) so the daily `traveller-documents:cleanup` job runs. Configure the active web-server PHP `upload_max_filesize` above 5 MB and `post_max_size` above the maximum combined booking upload (up to 80 MB for a 16-traveller cruise); the existing 500M/510M settings satisfy this.

The configured tax rate is `TRAVEL_TAX_RATE` in `.env` (defaults to `0.07`); promo-code stacking on package deals is controlled by `TRAVEL_STACK_PROMO_ON_DEALS` (defaults to `true`).

Cruise packages are linked to their prototype fleet ships, and guests can save journeys before signing in; saved guest journeys are added to the account when the guest logs in or registers.

## Administrator account

Set `ADMIN_NAME`, `ADMIN_EMAIL`, and a strong `ADMIN_PASSWORD` in `.env`, then run `php artisan db:seed`. The seeder creates or updates that administrator account. Admin access is at `/admin/login`. On first sign-in, register the displayed Base32 secret with an authenticator app and verify its 6-digit code. Public registration cannot grant administrator privileges.

Do not commit `.env` or production credentials. Configure a production database, mail transport, `APP_DEBUG=false`, HTTPS, backups, and your chosen payment provider before accepting paid reservations. Checkout currently records a pending booking request; it does not collect payment.

## Sending email

To deliver enquiry replies, configure `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME` in `.env`. Do not commit mail credentials. With the default `log` mail driver, messages are written to `storage/logs` instead of being delivered to customers.

## Customer account security

- **Passwords** need at least 10 characters (maximum 128), including a letter and a number. Any characters are allowed. Existing passwords keep working until they are changed.
- **Two-factor sign-in:** when a customer turns it on (Account > Security), each login sends a 6-digit code to their account email. Codes expire after 10 minutes and allow 5 attempts. The toggle needs a verified email address, and changing the email address turns it off until the new address is verified.
- **Email verification:** a verification email is sent at registration and when the email address changes. It does not block booking or checkout; unverified customers see a reminder on their account page.
- **Ending other sign-ins:** changing the password, resetting it, or choosing "Log out from all devices" ends the customer's other sessions. Listing sessions requires `SESSION_DRIVER=database`; with other drivers only "remember me" tokens are reset. Changing or resetting a password also forgets any "trust this device" admin cookies.
- **Emails** are sent immediately, and a failure is logged without breaking the page the customer is on. No queue worker is required.
- **Checkout** saves bookings as "Pending payment" first, then charges the payment gateway, then marks them paid. A declined payment removes the pending bookings and keeps the cart. If the gateway errors and the result is unknown, the bookings stay pending and are logged for you to reconcile.

## Validation

```powershell
php artisan test
php artisan route:list
```

The site uses responsive CSS breakpoints for phone, tablet, laptop, and desktop widths. It is self-contained in this directory and does not depend on the former React/Vite prototype.
