# Tirana Solidare - Developer Setup Guide


## 1. The Tools
If you haven't already, grab these:
*   **XAMPP**: [apachefriends.org](https://www.apachefriends.org/) (This installs PHP, Apache, and MySQL for us).
*   **Git**: [git-scm.com](https://git-scm.com/) (To sync our code).
*   **VS Code**: Recommended editor.

## 2. Clone the Project
XAMPP only serves websites that are inside its `htdocs` folder.

1.  Navigate to your XAMPP folder (usually `C:\xampp\htdocs`).
2.  Right-click and select **Git Bash Here** (or open a terminal).
3.  Run this command to download our code:
    ```bash
    git clone https://github.com/your-username/tirana-solidare.git TiranaSolidare
    ```
    *Important: Keep the folder name `TiranaSolidare` exactly as is, or the CSS/JS paths might break.*

## 3. Start the Server
1.  Open the **XAMPP Control Panel**.
2.  Click **Start** next to **Apache** (This is the web server).
3.  Click **Start** next to **MySQL** (This is the database).

**⚠️ Important Note on Ports:**
Look at the "Port" number next to MySQL in XAMPP.
*   If it says **3307**: You are good to go.
*   If it says **3306** (Standard): You need to edit `config/db.php`. Change `port=3307` to `port=3306` (or remove the port part entirely).

## 4. Set Up the Database (phpMyAdmin)
We need to import our database structure so the login works.

1.  In XAMPP, click the **Admin** button next to MySQL (or go to `http://localhost/phpmyadmin`).
2.  Click **New** in the left sidebar.
3.  Database Name: `TiranaSolidare` (Make sure it matches exactly).
4.  Click **Create**.
5.  Click the **Import** tab at the top.
6.  Click **Choose File** and find `database/schema.sql` inside our project folder.
7.  Click **Import** at the bottom of the page.

## 5. Run the App
Open your browser and go to:
`http://localhost/TiranaSolidare/`

You should see the login screen.

## 6. How to Push Code (GitHub)
**Before you start working:**
Always pull the latest changes so you don't overwrite someone else's work.
```bash
git pull
```

**When you finish a task:**
1.  `git add .` (Stages your files)
2.  `git commit -m "Fixed the login button"` (Describes what you did)
3.  `git push` (Sends it to GitHub)

---
**Troubleshooting**
*   **"Database connection failed"**: Check `config/db.php`. For XAMPP, user is usually `root` and password is empty `''`.
*   **Styles look weird**: Press `Ctrl + F5` in your browser to force a refresh.
