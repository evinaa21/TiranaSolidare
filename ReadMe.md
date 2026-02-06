# 🤝 Tirana Solidare - Volunteering & Mutual Aid Platform

![Project Status](https://img.shields.io/badge/status-in%20development-orange)
![PHP Version](https://img.shields.io/badge/PHP-8.0%2B-blue)
![License](https://img.shields.io/badge/license-MIT-green)

**Tirana Solidare** is a digital ecosystem designed to empower volunteering and mutual aid in the city of Tirana. The platform connects the Municipality, volunteers, and citizens in need through a secure and interactive web interface.

---

## 📖 Table of Contents
- [Features](#-features)
- [Tech Stack](#-tech-stack)
- [Database Architecture](#-database-architecture)
- [Installation & Setup](#-installation--setup)
- [Folder Structure](#-folder-structure)
- [Screenshots](#-screenshots)

---

## 🚀 Features

### 👤 For Users (Volunteers & Citizens)
* **User Authentication:** Secure login/registration with hashed passwords.
* **Event Application:** Browse events and apply to volunteer with a single click.
* **Live Notifications:** Real-time updates on application status via **AJAX Polling**.
* **Interactive Dashboard:** View application history and profile status.
* **Need Requests:** Post specific requests for help (social, humanitarian).

### 🛡️ For Administrators (Municipality)
* **Event Management:** Create, edit, and delete volunteering events.
* **Application Review:** Accept or reject volunteer applications.
* **Analytics Dashboard:** Visual statistics using **Chart.js** (Volunteers per month, top categories).
* **User Management:** Manage roles and suspend accounts if necessary.

---

## 🛠 Tech Stack

### Frontend (Client-Side)
* **HTML5 & CSS3:** Semantic structure and custom styling.
* **Bootstrap 5:** For responsive layout and mobile-friendly UI components.
* **JavaScript (ES6):** Dynamic interactions and DOM manipulation.
* **SweetAlert2:** Beautiful, responsive popup notifications instead of standard browser alerts.
* **FontAwesome:** Vector icons for better UX.

### Backend (Server-Side)
* **PHP (8.x):** Core business logic, session management, and routing.
* **PHPMailer:** SMTP integration for sending transactional emails (Registration, Password Reset).

### Database
* **MySQL:** Relational database management.
* **PDO (PHP Data Objects):** For secure database connections to prevent SQL Injection.

---

## 🗄 Database Architecture

The system is built on a relational database schema designed for scalability. Key entities include:

* **Users (`Perdoruesi`):** Stores credentials and roles (Admin/Volunteer).
* **Events (`Eventi`):** Stores event details, dates, and locations.
* **Applications (`Aplikimi`):** A junction table linking Users to Events with status tracking.
* **Notifications (`Njoftimi`):** System-generated alerts for users.

*(See `database/schema.sql` for the full ERD implementation)*

---

## ⚙ Installation & Setup

Follow these steps to run the project locally:

1.  **Clone the Repository**
    ```bash
    git clone [https://github.com/your-username/tirana-solidare.git](https://github.com/your-username/tirana-solidare.git)
    ```

2.  **Set up the Environment**
    * Install **XAMPP** (or WAMP/MAMP).
    * Move the project folder to `C:/xampp/htdocs/tirana-solidare`.

3.  **Database Configuration**
    * Open `phpMyAdmin` (http://localhost/phpmyadmin).
    * Create a new database named `tirana_solidare_db`.
    * Import the `database.sql` file located in the `db/` folder of this repo.
    * Update database credentials in `config/db_connect.php`:
        ```php
        $host = 'localhost';
        $db   = 'tirana_solidare_db';
        $user = 'root';
        $pass = '';
        ```

4.  **Run the Project**
    * Open your browser and navigate to: `http://localhost/tirana-solidare`

---

## 📂 Folder Structure

```text
/tirana-solidare
├── /admin              # Administrator dashboard & logic
├── /assets
│   ├── /css            # Custom styles & Bootstrap overrides
│   ├── /js             # AJAX scripts, SweetAlert config
│   └── /images         # Project assets
├── /config             # Database connection & global constants
├── /includes           # Reusable components (Header, Footer, Navbar)
├── /database           # SQL dump files
├── index.php           # Landing page
└── README.md           # Project documentation
