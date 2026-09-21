# Maintenance & Operations Guide

This document covers recurring maintenance routines, database backup strategies, log management, and password migration procedures.

---

## 1. Database Backup & Disaster Recovery

Because attendance and internal assessment marks constitute official statutory records, regular backups are essential.

### 1.1 Automated Nightly Backup Script
Create a scheduled cron task to dump the database:

```bash
#!/bin/bash
BACKUP_DIR="/var/backups/attendance_db"
TIMESTAMP=$(date +"%Y%m%d_%H%M%S")
DB_NAME="u182589698_jntuaceasarb"
DB_USER="u182589698_jntuaceasarb"

mkdir -p "$BACKUP_DIR"

# Perform compressed dump
mysqldump -u "$DB_USER" -p'YourPassword' "$DB_NAME" | gzip > "$BACKUP_DIR/backup_${DB_NAME}_${TIMESTAMP}.sql.gz"

# Retain backups for 30 days
find "$BACKUP_DIR" -name "backup_*.sql.gz" -mtime +30 -delete
```

---

## 2. Password Hash Migration (MD5 to BCrypt)

The system automatically upgrades passwords from legacy MD5 to BCrypt as users log in.

### 2.1 How the Automatic Migration Operates
In `User::authenticate()` (`user.class.php`):
1. User enters plain password $P$.
2. The database stores hash $H$.
3. If $H$ does not begin with `$2y$` (BCrypt salt marker) and `md5($P) === $H`:
   - Authentication succeeds.
   - `password_hash($P, PASSWORD_BCRYPT)` computes a new secure hash.
   - The user record is updated immediately:
     ```sql
     UPDATE users SET password = ? WHERE id = ?
     ```
   - A log entry is appended to `logs/activity_*.log`.

### 2.2 Auditing Migration Progress
To inspect how many user accounts have completed migration to BCrypt:

```sql
-- Total accounts vs BCrypt vs Legacy MD5
SELECT 
    COUNT(*) AS total_users,
    SUM(CASE WHEN password LIKE '$2y$%' THEN 1 ELSE 0 END) AS bcrypt_upgraded,
    SUM(CASE WHEN password NOT LIKE '$2y$%' THEN 1 ELSE 0 END) AS legacy_md5
FROM users;
```

---

## 3. Log Rotation & Monitoring

### 3.1 Flat-File Logs (`logs/`)
The `Logs` class writes logs partitioned by date:
- `logs/activity_YYYY-MM-DD.log`
- `logs/error_YYYY-MM-DD.log`

Set up a logrotate policy or cron job to compress and prune files older than 60 days:
```bash
# Prune old logs older than 60 days
find /Applications/XAMPP/xamppfiles/htdocs/classattendance.in/jntuacea/logs/ -name "*.log" -mtime +60 -delete
```

### 3.2 Database Audit Tables (`activity_logs` & `fac_activity_logs`)
For performance optimization, audit logs can be archived annually into an archive table:
```sql
-- Archive logs older than 1 year
CREATE TABLE IF NOT EXISTS activity_logs_archive LIKE activity_logs;
INSERT INTO activity_logs_archive SELECT * FROM activity_logs WHERE timestamp < NOW() - INTERVAL 1 YEAR;
DELETE FROM activity_logs WHERE timestamp < NOW() - INTERVAL 1 YEAR;
```

---

## 4. Academic Term Rollover Checklist

At the beginning of a new academic semester:
1. **SuperAdmin**:
   - Create the new academic year entry in `superadminacademicyears.php` if transitioning to a new year.
   - Generate new class sections in `superadminclasses.php`.
   - Update class timing schedules in `class_timing_schedule` if semester class timings change.
2. **Admin**:
   - Enroll incoming cohorts into classes (`adminenrollstudents.php`).
   - Create faculty accounts for new teaching appointments.
3. **HOD**:
   - Assign faculty to subjects (`hodmapfaculty.php`).
   - Map elective choices to students (`hodmapstudents.php`).
   - Build and publish weekly timetables (`hodmanage_timetable.php`).
