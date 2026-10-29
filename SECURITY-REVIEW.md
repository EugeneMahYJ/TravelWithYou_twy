# Security Review

**Review date:** 2026-10-29  
**Scope:** Read-only review of the Laravel application source. This review does not assess production infrastructure or establish that no other vulnerabilities exist.

## Confirmed finding

| # | Severity | File | Lines | Vulnerability | Confidence |
|---|----------|------|-------|---------------|------------|
| 1 | 🟡 MEDIUM | [CredentialRules.php](./laravel-site/app/Support/CredentialRules.php) | 7-12 | Password policy accepts passwords as short as six characters if they contain a letter and a digit. The shared rule is used for registration, password reset, and account password changes, allowing weaker passwords and increasing the risk of guessing or credential-stuffing compromise. | 8/10 |

## Security mechanisms confirmed in code

- **Password storage and reset:** Laravel's hashed password cast and password broker/token reset flow are used. See [User.php](./laravel-site/app/Models/User.php), [AuthController.php](./laravel-site/app/Http/Controllers/AuthController.php), and [auth.php](./laravel-site/config/auth.php).
- **Admin authentication and authorization:** Admin routes require authentication and the `admin` middleware. The middleware checks admin status, requires MFA verification, and logs out inactive sessions after 15 minutes. See [web.php](./laravel-site/routes/web.php), [EnsureAdmin.php](./laravel-site/app/Http/Middleware/EnsureAdmin.php), and [Totp.php](./laravel-site/app/Support/Totp.php). TOTP uses a random secret, HMAC-SHA1, 30-second codes, a limited adjacent time window, and constant-time comparison.
- **Request protections:** Login, registration, password-reset requests, and admin MFA verification have request throttles. Web routes use Laravel's web middleware (including CSRF protection), and account forms include CSRF tokens. See [web.php](./laravel-site/routes/web.php), [bootstrap/app.php](./laravel-site/bootstrap/app.php), and [account.blade.php](./laravel-site/resources/views/account.blade.php).
- **Sensitive data encryption:** Laravel encrypted casts protect MFA secrets, traveller details, and booking passport fields. The configured cipher is AES-256-CBC and uses `APP_KEY`. See [User.php](./laravel-site/app/Models/User.php), [Booking.php](./laravel-site/app/Models/Booking.php), and [app.php](./laravel-site/config/app.php).
- **Server-side booking and pricing validation:** Booking availability and checkout data are checked server-side; totals are recomputed from package data, promo codes are resolved server-side, and booking creation uses a database transaction. See [TravelController.php](./laravel-site/app/Http/Controllers/TravelController.php) and [Pricing.php](./laravel-site/app/Support/Pricing.php).
- **Admin media handling:** Media upload is behind admin routes and validates MIME type and size. Managed-path checks reject traversal segments for rename/delete operations. See [web.php](./laravel-site/routes/web.php), [AdminController.php](./laravel-site/app/Http/Controllers/AdminController.php), and [AssetLibrary.php](./laravel-site/app/Support/AssetLibrary.php).
- **Search query handling:** Search values are parameter-bound, and the query column names come from fixed code-defined lists. See [TravelController.php](./laravel-site/app/Http/Controllers/TravelController.php).

## Items not verifiable from this repository

- **Production payment processing:** The repository provides a simulated gateway for local/testing; it does not include a production payment-provider implementation. Production charging, settlement, and webhook validation cannot be assessed here. See [AppServiceProvider.php](./laravel-site/app/Providers/AppServiceProvider.php), [SimulatedPaymentGateway.php](./laravel-site/app/Payments/SimulatedPaymentGateway.php), and [TravelController.php](./laravel-site/app/Http/Controllers/TravelController.php).
- **Uploaded media execution:** Uploads are stored under public assets and the saved filename extension is derived from the client filename. Whether a file could execute as active content depends on web-server configuration, which is not included in the repository. See [AssetLibrary.php](./laravel-site/app/Support/AssetLibrary.php) and [filesystems.php](./laravel-site/config/filesystems.php).
- **Deployment configuration:** Effective production values for secure session cookies and debug mode depend on deployment environment settings and cannot be inferred from source defaults alone. See [session.php](./laravel-site/config/session.php) and [app.php](./laravel-site/config/app.php).
