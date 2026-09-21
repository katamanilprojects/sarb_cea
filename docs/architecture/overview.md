# System Architecture Overview

This document describes the architectural layout, runtime environment, authentication mechanisms, and logging infrastructure of the JNTUACEA Class Attendance & CIA Marks Management System.

---

## 1. Technical Stack

- **Runtime**: PHP 7.4+ / PHP 8.x
- **Web Server**: Apache 2.4+ (typically hosted via XAMPP / LAMP stack)
- **Database**: MariaDB 10.4+ / MySQL 5.7+ (`utf8mb4` charset)
- **Frontend**: Server-rendered HTML5, Bootstrap, jQuery, DataTables, FontAwesome, Chart.js
- **Configuration Mechanism**: PHP constant definitions in parent directory `.env.php`

---

## 2. Architectural Pattern

The application follows a **Hybrid Page-Controller / Domain-Service** architectural pattern:

1. **Page Controllers (`*.php`)**:
   - Serve as entry points for specific URL requests (e.g., `facaddattendance.php`, `adminenrollstudents.php`, `hodmanage_timetable.php`).
   - Manage request parsing, session verification, authorization guards, and HTML rendering.
   - Include shared layout fragments (`header.php`, `menu.php`, `footer.php`).

2. **Domain Service Models (`*.class.php`)**:
   - Encapsulate all database operations, business logic, transaction handling, and validations.
   - Return structured associative arrays indicating status and payloads (e.g., `['status' => 1, 'data' => ...]`).

3. **AJAX Endpoints**:
   - Lightweight PHP scripts (`ajax_handler.php`, `ajax_get_classes.php`, `ajax_get_timings.php`) that read `$_GET` / `$_POST`, invoke model classes, and return JSON responses to dynamic UI widgets.

---

## 3. Security & Authentication Architecture

### 3.1 Session & Brute-Force Guard
Authentication is handled in `index.php` using the `User` class:
- **Rate-Limiting / Lockout**: Tracks failed login attempts per username using session keys:
  - `login_attempts_md5(username)`: Increments on every invalid password attempt.
  - `login_locked_md5(username)`: Enforces a 15-minute lockout (`lockDuration = 900`) once attempts reach 5 (`maxAttempts = 5`).
- **Session Fixation Prevention**: Calls `session_regenerate_id(true)` immediately upon successful authentication.
- **Session Context Variables**:
  - `$_SESSION['user']`: Username / Roll number (lowercase string).
  - `$_SESSION['userid']`: Primary key integer in `users` table.
  - `$_SESSION['role']`: Normalized role string (`superadmin`, `admin`, `academic_section`, `hod`, `faculty`, `student`).
  - `$_SESSION['name']`: Uppercase display name.
  - `$_SESSION['dept_id']`: Department ID (set for HOD role).
  - `$_SESSION['facid']`: Faculty record ID (set for Faculty role).

### 3.2 Transparent Password Hash Migration (MD5 to BCrypt)
The system supports smooth legacy migration from MD5 to standard PHP `password_hash()` (BCrypt):
1. When a user submits credentials, `User::authenticate()` inspects the stored password hash in `users.password`.
2. If the hash begins with `$2y$` (standard BCrypt prefix), it verifies via `password_verify($password, $dbPassword)`.
3. If it is legacy MD5:
   - It checks `md5($password) === $dbPassword`.
   - On match, it immediately generates a new BCrypt hash via `password_hash($password, PASSWORD_BCRYPT)`.
   - It updates the database record silently:
     ```sql
     UPDATE users SET password = ? WHERE id = ?
     ```
   - Logs the upgrade event: `Password silently upgraded MD5->bcrypt for user: <username>`.

---

## 4. Logging & Audit Subsystems

The application employs a dual logging strategy combining flat-file logs with database audit tables:

### 4.1 Flat-File Logging (`logs.class.php`)
Located in the `logs/` directory with daily-stamped log files:
- **Activity Logs** (`logs/activity_YYYY-MM-DD.log`): Records successful logins, password updates, password resets, and critical workflow events via `Logs::activityLog()`.
- **Error Logs** (`logs/error_YYYY-MM-DD.log`): Records SQL prepare/execute failures, database connection errors, and caught exceptions via `Logs::errLog()`.

### 4.2 Database Audit Trails
For regulatory audit compliance:
- **`activity_logs` Table**: Records general administrative actions (`user_id`, `action`, `details`, `timestamp`).
- **`fac_activity_logs` Table**: Specifically tracks faculty activities such as attendance marking, diary updates, and marks entry (`user_id` referencing `faculties.id`, `action`, `details`, `timestamp`).

---

## 5. Transaction Safety

Critical operations (such as attendance marking, timetable slot booking, student enrollment, and attendance deletion) wrap multiple SQL queries inside atomic database transactions:
```php
$this->conn->begin_transaction();
try {
    // 1. Insert diary record
    // 2. Insert attendance records for all mapped students
    // 3. Log to fac_activity_logs
    $this->conn->commit();
} catch (Exception $e) {
    $this->conn->rollback();
    $this->logs->errLog("Transaction failed: " . $e->getMessage());
}
```
This guarantees zero orphaned attendance records or inconsistent diary entries.
