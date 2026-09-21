# Installation & Configuration Guide

This guide details the technical requirements, environment setup, database installation, and file system permissions necessary to run the application.

---

## 1. System Requirements

- **PHP**: Version 7.4 or 8.0+ (requires extensions: `mysqli`, `session`, `json`, `mbstring`, `curl`, `openssl`).
- **Web Server**: Apache 2.4+ (mod_rewrite enabled, `.htaccess` override allowed).
- **Database**: MariaDB 10.4+ or MySQL 5.7+ / 8.0+ (`utf8mb4` character set).
- **Hosting Stack**: Tested on standard XAMPP / LAMP / WAMP environments.

---

## 2. Directory Layout & Repository Placement

In a standard XAMPP setup:
```text
/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/
├── .env.php                  <-- Environment configuration file (CRITICAL)
└── jntuacea/                 <-- Project root directory
    ├── index.php
    ├── dbcredentials.class.php
    ├── user.class.php
    ├── logs/
    ├── uploads/
    └── ...
```

---

## 3. Environment Configuration (`.env.php`)

> [!IMPORTANT]
> The database connection class (`DBCredentials` in `dbcredentials.class.php`) explicitly requires the configuration file located in the **parent directory**:
> ```php
> if (!defined('DB_HOST')) {
>     require_once dirname(__DIR__) . '/.env.php';
> }
> ```
> It does **not** read a standard `.env` text file.

Create or verify the `.env.php` file at `../.env.php` relative to the project root:

```php
<?php
// .env.php (Placed in parent directory of jntuacea)
define('DB_HOST', 'localhost');
define('DB_USER', 'u182589698_jntuaceasarb');
define('DB_PASS', 'YourStrongPasswordHere');
define('DB_NAME', 'u182589698_jntuaceasarb');
```

---

## 4. Database Setup & Initialization

1. Create the database in MariaDB / MySQL:
   ```sql
   CREATE DATABASE `u182589698_jntuaceasarb` 
   CHARACTER SET utf8mb4 
   COLLATE utf8mb4_general_ci;
   ```

2. Import the schema file `u182589698_jntuaceasarb_database_scheme.sql`:
   ```bash
   mysql -u u182589698_jntuaceasarb -p u182589698_jntuaceasarb < u182589698_jntuaceasarb_database_scheme.sql
   ```

3. Verify that the 48 core tables and constraints are loaded successfully:
   ```sql
   USE u182589698_jntuaceasarb;
   SHOW TABLES;
   ```

---

## 5. File System Permissions

The application requires write access for logging and file attachments:

```bash
# Ensure web server (www-data, daemon, or _www on macOS) has write access:
chmod -R 775 logs/
chmod -R 775 uploads/

# Ensure subdirectories exist inside uploads/
mkdir -p uploads/cia_attachments
mkdir -p uploads/syllabus
chmod -R 775 uploads/cia_attachments
chmod -R 775 uploads/syllabus
```

---

## 6. Web Server Configuration

### Apache VirtualHost or Directory
Ensure `AllowOverride All` is set so that `.htaccess` rules (session settings and headers) are applied:

```apache
<Directory "/Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea">
    Options Indexes FollowSymLinks
    AllowOverride All
    Require all granted
</Directory>
```

### Verification
Open `http://localhost/classattendance.in/jntuacea/` in your web browser. You should be greeted with the JNTUACEA Attendance System login portal.
