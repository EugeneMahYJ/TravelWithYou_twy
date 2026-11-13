# Travel With You

A Laravel 12 + Blade travel storefront and administration area styled after the existing Travel With You React interface.

## Requirements

- PHP 8.2+
- Composer 2+
- SQLite (enabled by default) or another Laravel-supported database
- PHP extensions required by Laravel and the selected database driver
- A web server configured to serve the `public/` directory over HTTPS

## Production deployment

### 1. Prepare the host and release

- Deploy the contents of `laravel-site/` from a clean source checkout. Do not publish `twy_website.zip` or copy your development `.env`, SQLite database, logs, or `storage/app/private` files into a release.
- Set the site's document root to the release's `public/` directory. Do not expose the Laravel project root, `.env`, `database/`, or `storage/` through the web server.
- Use the same supported PHP version and extensions for the command-line PHP and the web-server PHP. Enable HTTPS before accepting customer sign-ins or uploads.
- Make `storage/` and `bootstrap/cache/` writable by the PHP worker. Also make `public/assets/uploads/` writable if administrators will upload public media. Keep `storage/app/private/` private and back it up securely.

From the deployed project directory, install production dependencies:

```sh
composer install --no-dev --prefer-dist --optimize-autoloader
```

### 2. Configure production environment

Create the production `.env` on the server or configure the same values through the hosting provider's secret manager. Never commit it, include it in a release archive, or reuse the local development `.env`.

At minimum, configure:

```dotenv
APP_NAME="Travel With You"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain
LOG_LEVEL=warning

DB_CONNECTION=sqlite
DB_DATABASE=

SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
FILESYSTEM_LOCAL_SERVE=false

ADMIN_NAME="Travel With You Admin"
ADMIN_EMAIL=your-admin-email
ADMIN_PASSWORD=use-a-unique-strong-password
```

Use the database credentials and host supplied by your provider. SQLite is also supported if the host provides persistent storage: set `DB_CONNECTION=sqlite` and `DB_DATABASE` to an absolute path to a production SQLite file outside the public web root, and ensure PHP can write to both the file and its directory. Do not copy the development `database/database.sqlite` to production. Keep the database, private traveller documents, and uploaded files on persistent storage; back them up and test restoring them.

Generate `APP_KEY` once for a new production installation with `php artisan key:generate`, then keep it secret and unchanged across deployments. It protects encrypted application data; changing it can make existing encrypted data unreadable. Do not generate a new key during routine releases.

Configure outbound email before relying on verification, sign-in codes, booking confirmations, or enquiry replies. For Resend, set `MAIL_MAILER=resend`, `RESEND_API_KEY`, `MAIL_FROM_ADDRESS`, and `MAIL_FROM_NAME`, and verify the sender domain with the provider. For SMTP, set `MAIL_MAILER=smtp` and the provider's `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and sender address. `MAIL_MAILER=log` writes messages to application logs instead of delivering them.

### 3. Initialize and release

On a new production database, run:

```sh
php artisan migrate --force
php artisan db:seed --force
php artisan config:cache
php artisan view:cache
```

Run the seeder only when initializing a fresh production database: it seeds the catalogue and creates or updates the administrator from `ADMIN_NAME`, `ADMIN_EMAIL`, and `ADMIN_PASSWORD`. On later releases, run migrations and refresh the configuration/view caches as needed; do not rerun the seeder unless you intend to update seeded data and the administrator account.

After deployment, verify the home page over HTTPS, administrator sign-in and MFA setup, customer email delivery, booking/enquiry flows, file upload/download permissions, and a database backup/restore. Recheck these after changing environment values or upgrading dependencies.

The application schedules daily cleanup of expired staged and booked traveller documents and old search-term counts. Configure the host scheduler to run Laravel's scheduler every minute:

```cron
* * * * * cd /absolute/path/to/laravel-site && php artisan schedule:run >> /dev/null 2>&1
```

Admin media uploads can be as large as 500 MB. If that feature is needed, configure PHP `upload_max_filesize=500M` and `post_max_size=510M`, plus compatible web-server/proxy body limits, temporary storage, and request timeouts. Do not raise upload limits unless the host has enough capacity and the feature is needed.

### Payment processing is not production-ready

The current payment gateway is simulated and is bound only in local/testing environments. No live payment provider is configured for production. The `PAYMENT_*_ENABLED` settings do not connect a payment processor. Do not represent bookings as paid or accept live card payments until a real provider integration, its callback/webhook handling, and payment reconciliation have been implemented and tested.

