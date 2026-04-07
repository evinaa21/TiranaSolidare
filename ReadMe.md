#   Tirana Solidare - Volunteering & Mutual Aid Platform

![Project Status](https://img.shields.io/badge/status-in%20development-orange)
![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)



**Tirana Solidare** is a web-based platform designed to facilitate civic engagement and social aid management within the Municipality of Tirana. The system implements a secure Client-Server architecture to connect municipal administrators, volunteers, and citizens in need through a centralized interface.

## Project Abstract

The objective of this project is to digitize the volunteer management process. The platform addresses the need for a scalable system to handle event scheduling, volunteer application tracking, and resource allocation. It features Role-Based Access Control (RBAC) to differentiate between administrative privileges and public access, ensuring data integrity and user privacy.

## Technical Architecture

The application is built using a native LAMP stack (Linux, Apache, MySQL, PHP) approach without reliance on heavy backend frameworks, ensuring optimized performance and full control over the execution flow.

### Technology Stack

* **Backend:** PHP 8.x (Procedural/Object-Oriented hybrid approach).
* **Database:** MySQL (InnoDB engine) with normalized relational schema (3NF).
* **Frontend:** HTML5, CSS3, JavaScript (ES6+).
* **Async Operations:** AJAX (Fetch API) for non-blocking data updates.
* **Security:**
    * `PDO` prepared statements for SQL injection prevention.
    * `password_hash()` (Bcrypt) for credential storage.
    * CSRF tokens on all state-changing requests.
    * XSS sanitization on output.

### Database Design

The system relies on a strictly relational model including:
* **Users & RBAC:** Centralized user table with role delineation (`super_admin`, `admin`, `organizer`, `volunteer`).
* **Event Management:** One-to-Many relationships for categorization.
* **Application Tracking:** Junction tables handling Many-to-Many relationships between volunteers and events with status flags.
* **Help Requests:** Request/Offer matching system with moderation workflow.
* **Guardian Consent:** Under-16 registration requires parental approval via email link.

## Installation & Setup

### Prerequisites
* Apache HTTP Server (via XAMPP/WAMP or native installation).
* PHP 8.0 or higher with GD extension enabled.
* MySQL 8.0 or higher.
* Composer (for PHPMailer dependency).

### Local Development (XAMPP)

1.  **Clone the repository** into your web root:
    ```bash
    cd C:\xampp\htdocs
    git clone https://github.com/evinaa21/TiranaSolidare.git
    ```

2.  **Install dependencies:**
    ```bash
    cd TiranaSolidare
    composer install
    ```

3.  **Import the database:**
    * Open phpMyAdmin (`http://localhost/phpmyadmin`).
    * Create a database named `TiranaSolidare`.
    * Import `TiranaSolidare.sql`.

4.  **Run all migrations** (in order):
    ```bash
    php migrations/migrate_user_blocks.php
    php migrations/migrate_block_reason.php
    php migrations/migrate_help_request_type_values.php
    php migrations/migrate_help_request_flags.php
    php migrations/migrate_help_request_moderation.php
    php migrations/migrate_help_request_matching_flow.php
    php migrations/migrate_category_banner_support.php
    php migrations/migrate_guardian_consent.php
    php migrations/migrate_push_subscriptions.php
    php migrations/migrate_support_messages.php
    php migrations/migrate_platform_branding_and_organizations.php
    php migrations/migrate_public_org_applications.php
    php migrations/migrate_audit_fixes.php
    ```

5.  **Configure environment:**
    ```bash
    cp .env.example .env
    ```
    Edit `.env` and set:
    * `DB_PORT=3307` (XAMPP default; Linux servers typically use `3306`)
    * `DB_USER=root` and `DB_PASS=` (or your local credentials)
    * `APP_URL=http://localhost/TiranaSolidare`
    * SMTP settings (see [Email Setup](#email-setup) below)

6.  **Seed demo data** (optional):
    ```bash
    php migrations/seed_sample_content.php
    ```

7.  **Access the application:**
    * Public site: `http://localhost/TiranaSolidare/`
    * Login: `http://localhost/TiranaSolidare/views/login.php`

### Production Deployment

1.  **Clone and install** on your server:
    ```bash
    git clone https://github.com/evinaa21/TiranaSolidare.git /var/www/html/TiranaSolidare
    cd /var/www/html/TiranaSolidare
    composer install --no-dev
    ```

2.  **Create and configure `.env`:**
    ```bash
    cp .env.example .env
    nano .env
    ```
    **Required production values:**
    ```env
    DB_HOST=localhost
    DB_PORT=3306
    DB_NAME=TiranaSolidare
    DB_USER=your_db_user
    DB_PASS=your_secure_password

    SMTP_HOST=smtp.gmail.com
    SMTP_PORT=587
    SMTP_USER=your-gmail@gmail.com
    SMTP_PASS=your-app-password
    SMTP_FROM=your-gmail@gmail.com
    SMTP_FROM_NAME=Tirana Solidare

    APP_URL=https://yourdomain.com/TiranaSolidare
    APP_TIMEZONE=Europe/Tirane
    ```

3.  **Import database and run migrations** (same as local setup, steps 3-4).

4.  **Set directory permissions:**
    ```bash
    chmod -R 755 uploads/
    chmod -R 755 public/assets/uploads/
    chown -R www-data:www-data uploads/ public/assets/uploads/
    ```

5.  **Apache configuration** — ensure `mod_rewrite` is enabled and `AllowOverride All` is set for the project directory.

6.  **PHP production settings** (in `php.ini`):
    ```ini
    display_errors = Off
    log_errors = On
    error_log = /var/log/php_errors.log
    ```

7.  **Security headers** — add to your Apache virtual host or `.htaccess`:
    ```apache
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
    ```

### Email Setup

The platform sends emails for: account verification, guardian consent, password reset, and notifications.

**Gmail SMTP (recommended for small deployments):**
1. Enable 2-Factor Authentication on your Google account.
2. Go to https://myaccount.google.com/apppasswords and generate an App Password.
3. Set in `.env`:
   ```env
   SMTP_HOST=smtp.gmail.com
   SMTP_PORT=587
   SMTP_USER=your-gmail@gmail.com
   SMTP_PASS=xxxx-xxxx-xxxx-xxxx
   SMTP_FROM=your-gmail@gmail.com
   ```

> **Note:** Gmail requires `SMTP_FROM` to equal `SMTP_USER`. Mail sent with a different From address will be rejected.

## Demo Accounts

After running `php migrations/seed_sample_content.php`, the following accounts are available. **All accounts use the password: `Demo123!`**

| Role | Email | Name | Description |
|------|-------|------|-------------|
| **Admin** | `demo.admin@tiranasolidare.local` | Administrator Demo | Full dashboard access, manages events/users/reports |
| **Volunteer** | `demo.elira@tiranasolidare.local` | Elira Gjoni | Active volunteer, food distribution & coordination |
| **Volunteer** | `demo.arber@tiranasolidare.local` | Arber Hoxha | Logistics, transport, and field operations |
| **Volunteer** | `demo.sara@tiranasolidare.local` | Sara Kola | Children's educational activities |
| **Volunteer** | `demo.leon@tiranasolidare.local` | Leon Tafa | Donation collection & social events |
| **Volunteer** | `demo.ina@tiranasolidare.local` | Ina Muca | Social visits & family aid coordination |

> **Important:** Change all demo account passwords or run `php migrations/clean_demo_database.php` before going to production.

## User Roles

| Role | Access Level |
|------|-------------|
| `super_admin` | Full platform control, can manage all admins |
| `admin` | Dashboard, user management, event/category CRUD, reports, exports |
| `organizer` | Event management for their own organization |
| `volunteer` | Browse events, apply, manage profile, contact help request posters |

## Core Modules

### 1. Authentication & Session Management
Secure login, registration, email verification, guardian consent (under-16), password reset. Sessions include CSRF rotation, session fixation protection, and inactivity timeout.

### 2. Volunteer Management System
Administrators create events with categories, dates, locations, and banners. Volunteers browse the catalog and submit applications. Admins accept/reject via the dashboard.

### 3. Help Requests & Offers
Citizens can post aid requests or volunteer offers. Matching system connects helpers with requesters. Moderation workflow ensures content quality.

### 4. Notification Engine
Real-time polling system for application status updates, event changes, and platform announcements.

### 5. Contact & Support
Public contact form with rate limiting. Internal support inbox for admin-user communication.

### 6. Reports & Exports
Admin HTML report with KPIs, category breakdown, top volunteers. CSV exports for users, events, and applications.

## Project Structure

```text
TiranaSolidare/
├── api/                  # JSON REST API endpoints
│   ├── helpers.php       # Shared middleware (auth, CORS, JSON, CSRF)
│   ├── auth.php          # Login, register, logout, verify, password reset
│   ├── events.php        # Event CRUD (list/get/create/update/delete)
│   ├── applications.php  # Volunteer application management
│   ├── categories.php    # Category CRUD
│   ├── export.php        # CSV/HTML report generation (admin)
│   ├── help_requests.php # Aid request/offer management
│   ├── messages.php      # Direct messaging between users
│   ├── notifications.php # Notification list, mark-read, delete
│   ├── organizations.php # Organization management
│   ├── settings.php      # Platform branding/settings (admin)
│   ├── stats.php         # Dashboard statistics
│   ├── support_messages.php # Support inbox
│   ├── upload.php        # Image upload handler
│   ├── users.php         # Admin user management
│   ├── geocode.php       # Location geocoding
│   └── push.php          # Web push subscription management
├── assets/js/            # JavaScript modules
│   ├── main.js           # Core UI, base path detection, navigation
│   ├── dashboard-ui.js   # Admin dashboard interactions
│   ├── ajax-polling.js   # Notification & event polling
│   └── map-component.js  # Map integration
├── config/
│   ├── db.php            # PDO database connection
│   ├── env.php           # .env file loader
│   └── mail.php          # SMTP configuration
├── includes/
│   ├── functions.php     # Shared helpers (auth, CSRF, email, uploads, etc.)
│   ├── status_labels.php # Status display mappings
│   └── web_push.php      # Web Push notification sender
├── migrations/           # Database migrations & seeders
├── public/               # Public-facing assets & components
│   ├── index.php         # Landing page
│   ├── assets/           # CSS, JS, uploads, images
│   └── components/       # Shared header, footer
├── src/actions/          # Form POST handlers (login, register, contact, etc.)
├── views/                # PHP view pages
│   ├── dashboard.php     # Admin panel
│   ├── volunteer_panel.php # Volunteer panel
│   ├── events.php        # Public event listing
│   ├── help_requests.php # Public aid requests
│   ├── login.php         # Authentication
│   ├── register.php      # Registration with guardian consent flow
│   └── ...               # Other views
├── tests/                # PHPUnit & Playwright tests
├── uploads/              # User-uploaded images
├── .env.example          # Environment template
├── TiranaSolidare.sql    # Base database schema
└── composer.json         # PHP dependencies
```

## Running Tests

```bash
# Unit & integration tests
./vendor/bin/phpunit

# End-to-end tests (requires Node.js)
npm install
npx playwright test
```

## License

This project is licensed under the MIT License.
### 6. Reports & Exports
Admin HTML report with KPIs, category breakdown, top volunteers. CSV exports for users, events, and applications.

## Project Structure

```text
TiranaSolidare/
├── api/                  # JSON REST API endpoints
│   ├── helpers.php       # Shared middleware (auth, CORS, JSON, CSRF)
│   ├── auth.php          # Login, register, logout, verify, password reset
│   ├── events.php        # Event CRUD (list/get/create/update/delete)
│   ├── applications.php  # Volunteer application management
│   ├── categories.php    # Category CRUD
│   ├── export.php        # CSV/HTML report generation (admin)
│   ├── help_requests.php # Aid request/offer management
│   ├── messages.php      # Direct messaging between users
│   ├── notifications.php # Notification list, mark-read, delete
│   ├── organizations.php # Organization management
│   ├── settings.php      # Platform branding/settings (admin)
│   ├── stats.php         # Dashboard statistics
│   ├── support_messages.php # Support inbox
│   ├── upload.php        # Image upload handler
│   ├── users.php         # Admin user management
│   ├── geocode.php       # Location geocoding
│   └── push.php          # Web push subscription management
├── assets/js/            # JavaScript modules
│   ├── main.js           # Core UI, base path detection, navigation
│   ├── dashboard-ui.js   # Admin dashboard interactions
│   ├── ajax-polling.js   # Notification & event polling
│   └── map-component.js  # Map integration
├── config/
│   ├── db.php            # PDO database connection
│   ├── env.php           # .env file loader
│   └── mail.php          # SMTP configuration
├── includes/
│   ├── functions.php     # Shared helpers (auth, CSRF, email, uploads, etc.)
│   ├── status_labels.php # Status display mappings
│   └── web_push.php      # Web Push notification sender
├── migrations/           # Database migrations & seeders
├── public/               # Public-facing assets & components
│   ├── index.php         # Landing page
│   ├── assets/           # CSS, JS, uploads, images
│   └── components/       # Shared header, footer
├── src/actions/          # Form POST handlers (login, register, contact, etc.)
├── views/                # PHP view pages
│   ├── dashboard.php     # Admin panel
│   ├── volunteer_panel.php # Volunteer panel
│   ├── events.php        # Public event listing
│   ├── help_requests.php # Public aid requests
│   ├── login.php         # Authentication
│   ├── register.php      # Registration with guardian consent flow
│   └── ...               # Other views
├── tests/                # PHPUnit & Playwright tests
├── uploads/              # User-uploaded images
├── .env.example          # Environment template
├── TiranaSolidare.sql    # Base database schema
└── composer.json         # PHP dependencies
```

## Running Tests

```bash
# Unit & integration tests
./vendor/bin/phpunit

# End-to-end tests (requires Node.js)
npm install
npx playwright test
```

## License

This project is licensed under the MIT License.
