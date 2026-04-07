# Production Checklist

Use this checklist before switching traffic to a live deployment.

## 1. Environment

- Copy `.env.example` to `.env` and set a real `APP_URL` with no trailing slash.
- Set production database credentials and verify the database user has read/write access.
- Set `SMTP_HOST`, `SMTP_USER`, `SMTP_PASS`, `SMTP_FROM`, and `SMTP_FROM_NAME`.
- Generate VAPID keys with `C:\xampp\php\php.exe migrations/generate_vapid.php` and store `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, and `VAPID_SUBJECT` in `.env`.
- Set `APP_TIMEZONE=Europe/Tirane` unless the deployment intentionally uses another timezone.

## 2. PHP And Apache

- Enable required PHP extensions: `pdo_mysql`, `openssl`, `curl`, `mbstring`, and `json`.
- Enable `gd` if you want image optimization plus full image-upload test coverage.
- Confirm `mod_rewrite` is enabled and `.htaccess` is honored by Apache.
- Ensure `public/assets/uploads` and `uploads/images/profiles` are writable by the web server user.

## 3. Background Jobs

- Schedule `ops/run-email-queue.bat` or `ops/run-email-queue.ps1` to run every 1-2 minutes.
- Confirm only one scheduler is active per deployment target.
- Review mail queue logs after the first live test send.

## 4. Validation

- Run `C:\xampp\php\php.exe ops/check-production-readiness.php` and resolve all failures.
- Run `C:\xampp\php\php.exe .\vendor\bin\phpunit`.
- Run `npx playwright test` with `E2E_BASE_URL` pointed at the deployment URL if it differs from local XAMPP.
- Verify outbound mail, push notifications, file uploads, and service-worker install from the real host.

## 5. First Live Smoke Test

- Register a new user and confirm verification email delivery.
- Submit a help request and confirm admin inbox + notification delivery.
- Create and approve an event, then confirm applicant notifications.
- Upload a profile photo and a site logo.
- Subscribe to push notifications and send a test notification.