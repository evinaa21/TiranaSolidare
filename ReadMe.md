#  Tirana Solidare - Volunteering & Mutual Aid Platform

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
tirana-solidare/
├── config/             # Database credentials and global constants
├── public/             # Publicly accessible assets (CSS, JS, Images)
├── src/
│   ├── Auth/           # Login/Register logic
│   ├── Controllers/    # Business logic for events and applications
│   └── Utils/          # Helper functions (Sanitization, Formatting)
├── templates/          # Reusable HTML components (Header, Footer)
├── database/           # SQL migration scripts
├── index.php           # Application entry point
└── README.md
