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
    * XSS sanitization on output.

### Database Design

The system relies on a strictly relational model including:
* **Users & RBAC:** Centralized user table with role delineation.
* **Event Management:** One-to-Many relationships for categorization.
* **Application Tracking:** Junction tables handling Many-to-Many relationships between volunteers and events with status flags.

## Core Modules

### 1. Authentication & Session Management
Handles secure login, registration, and session persistence. Includes middleware logic to restrict access to administrative routes based on the `role_type` attribute.

### 2. Volunteer Management System (VMS)
Allows administrators to create, update, and delete events. Volunteers can browse the catalog and submit applications. The system utilizes `AJAX` polling to update application statuses (Pending/Accepted/Rejected) without page reloads.

### 3. Notification Engine
A lightweight polling mechanism that checks for state changes in the `Njoftimi` table, providing real-time feedback to the user regarding their application status or platform announcements.

## Installation & Setup

### Prerequisites
* Apache HTTP Server (via XAMPP/WAMP or native installation).
* PHP 8.0 or higher.
* MySQL 8.0 or higher.

### Deployment Steps

1.  **Clone the repository**
    ```bash
    git clone [https://github.com/your-username/tirana-solidare.git](https://github.com/your-username/tirana-solidare.git)
    ```

2.  **Database Configuration**
    * Import `database/schema.sql` into your MySQL instance.
    * Configure the connection settings in `config/db.php`:
    ```php
    define('DB_HOST', 'localhost');
    define('DB_USER', 'root');
    define('DB_PASS', 'your_password');
    define('DB_NAME', 'tirana_solidare');
    ```

3.  **Directory Permissions**
    Ensure the `/uploads` directory is writable for event banner storage.

## Project Structure

```text
TiranaSolidare/
├── api/                  # JSON REST API endpoints
│   ├── helpers.php       # Shared middleware (auth, CORS, JSON helpers)
│   ├── auth.php          # Login, register, logout, current-user
│   ├── events.php        # Event CRUD (list/get/create/update/delete)
│   ├── applications.php  # Volunteer application management
│   ├── notifications.php # Notification list, mark-read, delete
│   ├── categories.php    # Category CRUD
│   ├── help_requests.php # Aid request/offer management
│   ├── users.php         # Admin user management (block/role/delete)
│   └── stats.php         # Dashboard & report statistics
├── assets/
│   ├── css/              # Custom styles (Bootstrap loaded via CDN)
│   └── js/
│       ├── ajax-polling.js # AJAX polling for notifications & events
│       └── main.js         # Admin panel & UI interactions
├── config/
│   └── db.php            # PDO database connection
├── includes/
│   ├── functions.php     # View helpers (auth check, flash, XSS)
│   ├── header.php        # HTML head + navbar
│   └── footer.php        # Footer + script includes
├── public/               # Public-facing landing page
├── src/actions/          # Form-based POST handlers
│   ├── login_action.php
│   ├── register_action.php
│   └── logout.php
├── views/                # Admin/auth PHP view pages
├── TiranaSolidare.sql    # Database schema
├── index.php             # Entry point (redirect)
└── README.md
```

## API Reference

All API endpoints return JSON with the structure:
```json
{
  "success": true,
  "data": { ... }
}
```
On error:
```json
{
  "success": false,
  "message": "Error description",
  "errors": ["field-level errors"]
}
```

### Authentication (`api/auth.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `POST` | `?action=login` | No | Log in with email & password |
| `POST` | `?action=register` | No | Register a new volunteer account |
| `POST` | `?action=logout` | Yes | Destroy session |
| `GET`  | `?action=me` | Yes | Get current user profile |

**Login example:**
```bash
curl -X POST http://localhost/TiranaSolidare/api/auth.php?action=login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"secret"}'
```

### Events (`api/events.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=list` | No | List events (paginated, filterable) |
| `GET` | `?action=get&id=1` | No | Get single event detail |
| `POST` | `?action=create` | Admin | Create a new event |
| `PUT` | `?action=update&id=1` | Admin | Update event fields |
| `DELETE` | `?action=delete&id=1` | Admin | Delete an event |

**Filters:** `?search=`, `?category=`, `?date_from=`, `?date_to=`, `?page=`, `?limit=`

### Applications (`api/applications.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=list` | Yes | My applications (volunteer) or all (admin) |
| `GET` | `?action=by_event&id=1` | Admin | Applications for a specific event |
| `POST` | `?action=apply` | Volunteer | Submit application for an event |
| `PUT` | `?action=update_status&id=1` | Admin | Accept/Reject an application |
| `DELETE` | `?action=withdraw&id=1` | Volunteer | Withdraw a pending application |

### Notifications (`api/notifications.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=list` | Yes | List all notifications |
| `GET` | `?action=unread_count` | Yes | Get unread notification count |
| `PUT` | `?action=mark_read&id=1` | Yes | Mark one notification as read |
| `PUT` | `?action=mark_all_read` | Yes | Mark all notifications as read |
| `DELETE` | `?action=delete&id=1` | Yes | Delete a notification |

### Categories (`api/categories.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=list` | No | List all categories with event counts |
| `POST` | `?action=create` | Admin | Create a new category |
| `PUT` | `?action=update&id=1` | Admin | Rename a category |
| `DELETE` | `?action=delete&id=1` | Admin | Delete a category |

### Help Requests (`api/help_requests.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=list` | No | List requests (filter: `?tipi=`, `?statusi=`) |
| `GET` | `?action=get&id=1` | No | Single request detail |
| `POST` | `?action=create` | Yes | Submit a request or offer |
| `PUT` | `?action=update&id=1` | Owner/Admin | Update request fields |
| `PUT` | `?action=close&id=1` | Owner/Admin | Close a request |
| `DELETE` | `?action=delete&id=1` | Admin | Delete a request |

### User Management (`api/users.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=list` | Admin | List all users (filter: `?roli=`, `?statusi=`, `?search=`) |
| `GET` | `?action=get&id=1` | Admin | User detail with stats |
| `PUT` | `?action=block&id=1` | Admin | Block a user |
| `PUT` | `?action=unblock&id=1` | Admin | Unblock a user |
| `PUT` | `?action=change_role&id=1` | Admin | Change user role |
| `DELETE` | `?action=delete&id=1` | Admin | Delete user and cascade data |

### Statistics & Reports (`api/stats.php`)

| Method | Action | Auth | Description |
|--------|--------|------|-------------|
| `GET` | `?action=overview` | Admin | Platform-wide dashboard stats |
| `GET` | `?action=my_stats` | Yes | Personal volunteer stats |
| `GET` | `?action=reports` | Admin | List generated reports |
| `POST` | `?action=generate` | Admin | Generate a new report |
