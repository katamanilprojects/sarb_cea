# Database Schema Reference

This document provides an exhaustive, field-by-field reference of all 48 tables, columns, data types, nullability, keys, and defaults extracted directly from `u182589698_jntuaceasarb_database_scheme.sql`.

---

## Functional Domain Index

The 48 tables in the database are organized into 8 functional domains:
1. [Identity, Access & Audit Logging](#1-identity-access--audit-logging) (6 tables)
2. [Academic Programs & Structure](#2-academic-programs--structure) (6 tables)
3. [Enrollment & Course Allotment](#3-enrollment--course-allotment) (2 tables)
4. [Daily Attendance & Teaching Diary](#4-daily-attendance--teaching-diary) (5 tables)
5. [Timetable Management](#5-timetable-management) (3 tables)
6. [Continuous Internal Assessment (CIA) & Marks](#6-continuous-internal-assessment-cia--marks) (11 tables)
7. [Outcome-Based Education (OBE) & Attainment](#7-outcome-based-education-obe--attainment) (8 tables)
8. [Physical Infrastructure, Surveys & Student Feedback](#8-physical-infrastructure-surveys--student-feedback) (7 tables)

---

## 1. Identity, Access & Audit Logging
Core user credentials, identity mapping across students and faculties, organizational departments, and transaction audit trails.

### 1.1 `users`
Master user accounts storing authentication credentials, contact details, and role assignments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | UNI | AUTO_INCREMENT | Unique user identifier (auto-increment surrogate key) |
| `username` | `varchar(20)` | No | PRI | - | Primary login identifier (student roll number, faculty username, dept code) |
| `password` | `varchar(255)` | No | - | - | Hashed password (BCrypt or upgraded legacy hash) |
| `name` | `varchar(100)` | No | - | - | Full legal name of the user |
| `mobile` | `varchar(20)` | Yes | - | NULL | User mobile contact number |
| `email` | `varchar(60)` | Yes | - | NULL | User email address |
| `role` | `varchar(20)` | No | - | - | Access control role: 'superadmin', 'admin', 'academic_section', 'hod', 'faculty', 'student' |
| `status` | `int(5)` | No | - | - | 1 = Active, 0 = Inactive / Suspended |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last modification timestamp |

> **Unique Keys**: (`id`)

### 1.2 `faculties`
Faculty member profiles linking login accounts to academic departments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Faculty profile entity ID |
| `username` | `varchar(30)` | No | MUL | - | Foreign key referencing users.username |
| `designation` | `varchar(30)` | No | - | - | Academic designation (Professor, Associate Professor, Assistant Professor, Adhoc Lecturer) |
| `facultyid` | `int(5)` | No | MUL | - | Optional foreign key referencing users.id |
| `dept_id` | `int(11)` | No | MUL | - | Foreign key referencing departments.id |
| `status` | `varchar(5)` | No | - | - | 1 = Active (eligible for timetable/allotment), 0 = Relieved/Inactive |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `facultyid` &rarr; `users(id)`, `username` &rarr; `users(username)`, `dept_id` &rarr; `departments(id)`

### 1.3 `students`
Student enrollment profiles linking roll numbers to academic cohorts and classes.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Student profile entity ID |
| `username` | `varchar(20)` | No | MUL | - | Roll number / Hall ticket number referencing users.username |
| `class_id` | `int(5)` | No | MUL | - | Foreign key referencing classes.id |
| `status` | `int(5)` | No | - | - | 1 = Active (enrolled), 0 = Detained / Inactive |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |
| `date_of_joining` | `date` | Yes | - | NULL | Official date of institutional admission |

> **Foreign Keys**: `class_id` &rarr; `classes(id)`, `username` &rarr; `users(username)`

### 1.4 `departments`
Academic departments across the institution.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Department ID |
| `username` | `varchar(30)` | No | - | - | HOD login username in users table |
| `dept_shortname` | `varchar(20)` | No | - | - | Short departmental acronym (e.g., EEE, ECE, CSE, MECH, CIVIL, CHEM) |
| `dept_fullname` | `varchar(100)` | No | - | - | Full official departmental title |
| `status` | `int(5)` | No | - | - | 1 = Active, 0 = Archived / Inactive |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

### 1.5 `activity_logs`
System-wide administrative audit trail capturing administrative transactions.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Log entry ID |
| `user_id` | `int(11)` | No | MUL | - | Foreign key referencing users.id |
| `action` | `varchar(255)` | No | - | - | Action classification (e.g., LOGIN, PASSWORD_RESET, ENROLL_STUDENT) |
| `details` | `text` | Yes | - | NULL | Descriptive audit details or JSON payload |
| `timestamp` | `datetime` | Yes | - | current_timestamp() | - |

> **Foreign Keys**: `user_id` &rarr; `users(id)`

### 1.6 `fac_activity_logs`
Dedicated audit log tracking faculty operations (attendance marking, diary entries, deletion requests).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Log entry ID |
| `user_id` | `int(11)` | No | MUL | - | Foreign key referencing faculties.id |
| `action` | `varchar(255)` | No | - | - | Faculty action code (e.g., ATTENDANCE_MARKED, DIARY_POSTED, DELETION_SUBMITTED) |
| `details` | `text` | Yes | - | NULL | Context details (subject_id, date, hour count, students affected) |
| `timestamp` | `datetime` | Yes | - | current_timestamp() | - |

> **Foreign Keys**: `user_id` &rarr; `faculties(id)`


## 2. Academic Programs & Structure
Hierarchical academic scaffolding: terms, degree programs, regulations, specializations, class cohorts, and subjects.

### 2.1 `academic_years`
Academic year terms (e.g., 2023-2024, 2024-2025).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Academic year ID |
| `acad_year` | `varchar(20)` | No | UNI | - | Academic year formatted label (e.g., 2024-2025) |
| `status` | `int(11)` | No | - | - | - |

> **Unique Keys**: (`acad_year`)

### 2.2 `programs`
Degree programs offered by the institution (e.g., B.Tech, M.Tech, MCA).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Program ID |
| `program_code` | `varchar(5)` | No | UNI | - | Unique degree program code (e.g., UG, PG) |
| `prog_shortname` | `varchar(20)` | No | - | - | Short code (e.g., B.Tech, M.Tech, MCA) |
| `prog_fullname` | `varchar(50)` | No | - | - | Full degree name (e.g., Bachelor of Technology, Master of Technology) |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`program_code`)

### 2.3 `regulations`
Academic regulations governing curriculum, attendance rules, and graduation criteria.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Regulation ID |
| `regulation` | `varchar(4)` | No | - | - | Regulation code string (e.g., R15, R19, R20, R23) |
| `prog_id` | `int(11)` | No | MUL | - | Foreign key referencing programs.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `prog_id` &rarr; `programs(id)`

### 2.4 `specialization`
Specialization branches/disciplines tied to departments and degree programs.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Specialization ID |
| `spec_code` | `varchar(20)` | No | - | - | Branch code (e.g., 01, 02, 03, 04, 05) |
| `spec_shortname` | `varchar(50)` | No | - | - | Branch short code (e.g., ECE, CSE, EEE, ME, CE) |
| `spec_fullname` | `varchar(100)` | No | - | - | Full specialization title (e.g., Computer Science and Engineering) |
| `dept_id` | `int(5)` | No | MUL | - | Foreign key referencing departments.id |
| `prog_id` | `int(5)` | No | MUL | - | Foreign key referencing programs.id |
| `status` | `tinyint(1)` | No | MUL | 1 | 1 = Active, 0 = Inactive / Discontinued |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `dept_id` &rarr; `departments(id)`, `prog_id` &rarr; `programs(id)`

### 2.5 `classes`
Academic classes/cohorts defining a group of students in an academic year, semester, and specialization.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Class cohort ID |
| `acad_year` | `varchar(10)` | No | UNI | - | Academic year string (e.g., 2024-2025) |
| `classname` | `varchar(60)` | No | - | - | Human-readable class label (e.g., B.Tech IV Year I Sem CSE) |
| `yearsem` | `varchar(20)` | No | UNI | - | Year and semester numeral (e.g., 11=I-I, 12=I-II, 21=II-I, 41=IV-I) |
| `start_date` | `date` | No | - | - | Semester instructional start date |
| `end_date` | `date` | No | - | - | Semester instructional end date |
| `status` | `int(11)` | No | - | 1 | 1 = Current / Active, 0 = Archived |
| `spec_id` | `int(5)` | No | UNI | - | Foreign key referencing specialization.id |
| `timing_id` | `int(11)` | No | - | 1 | Class timing slot group ID referencing class_timings.timing_id |
| `reg` | `varchar(10)` | No | - | - | Regulation name string (e.g., R20) |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`acad_year`,`yearsem`,`spec_id`)
> **Foreign Keys**: `spec_id` &rarr; `specialization(id)`, `reg_id` &rarr; `regulations(id)`

### 2.6 `subjects`
Curriculum subjects/courses taught within an academic class.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Subject ID |
| `subject_sno` | `varchar(3)` | No | - | - | Ordering serial number within the syllabus |
| `subcode` | `varchar(20)` | No | UNI | - | Official course catalog code (e.g., 20A05501T) |
| `sub_shortname` | `varchar(20)` | No | - | - | Subject short acronym (e.g., CN, OS, DBMS, DAA) |
| `sub_fullname` | `varchar(100)` | No | - | - | Full descriptive subject title (e.g., Computer Networks) |
| `sub_type` | `varchar(20)` | No | - | - | Course classification: Theory, Lab, Project, Comprehensive Viva, Mandatory Course |
| `class_id` | `int(5)` | No | UNI | - | Foreign key referencing classes.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`subcode`,`class_id`)
> **Foreign Keys**: `class_id` &rarr; `classes(id)`


## 3. Enrollment & Course Allotment
Junction entities mapping instructors and students to curriculum courses.

### 3.1 `faculty_sub`
Junction table assigning instructors to teach specific subjects.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Mapping ID |
| `faculty_id` | `int(5)` | No | UNI | - | Foreign key referencing faculties.id |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`faculty_id`,`sub_id`)
> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `sub_id` &rarr; `subjects(id)`

### 3.2 `student_sub`
Junction table enrolling individual students into specific subjects (supports core and elective enrollments).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Enrollment mapping ID |
| `stu_id` | `int(5)` | No | UNI | - | Foreign key referencing students.id |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`stu_id`,`sub_id`)
> **Foreign Keys**: `stu_id` &rarr; `students(id)`, `sub_id` &rarr; `subjects(id)`


## 4. Daily Attendance & Teaching Diary
Daily lecture period attendance marking, faculty syllabus diary coverage, deletion/correction workflows, and condonation rules.

### 4.1 `attendance`
Daily period-by-period student attendance tracking records.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Attendance record ID |
| `stu_id` | `int(5)` | No | UNI | - | Foreign key referencing students.id |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `date` | `varchar(10)` | No | UNI | - | Calendar date of attendance (YYYY-MM-DD) |
| `hour` | `int(11)` | No | UNI | - | Period / hour number (1 to 7) |
| `status` | `varchar(5)` | No | - | - | Attendance mark: 'P' = Present, 'A' = Absent |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`stu_id`,`sub_id`,`date`,`hour`)
> **Foreign Keys**: `stu_id` &rarr; `students(id)`, `sub_id` &rarr; `subjects(id)`

### 4.2 `diary`
Faculty daily teaching diary documenting syllabus content covered during each conducted period.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Diary record ID |
| `sub_id` | `int(5)` | No | UNI | - | Foreign key referencing subjects.id |
| `faculty_id` | `int(5)` | No | UNI | - | Foreign key referencing faculties.id |
| `date` | `varchar(10)` | No | UNI | - | Date of class conducted |
| `hour` | `int(11)` | No | UNI | - | Period / hour number (1 to 7) |
| `diary` | `varchar(255)` | No | MUL | - | Detailed description of topic / syllabus unit taught |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`sub_id`,`faculty_id`,`date`,`hour`)
> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `sub_id` &rarr; `subjects(id)`

### 4.3 `attendance_delete_requests`
Formal faculty requests to revoke erroneously marked attendance periods, subject to HOD review and approval.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Request ID |
| `faculty_id` | `int(11)` | No | MUL | - | Foreign key referencing faculties.id |
| `subject_id` | `int(11)` | No | MUL | - | Foreign key referencing subjects.id |
| `date` | `date` | No | - | - | Attendance date to be deleted |
| `hour` | `int(11)` | No | - | - | Period / hour number to be deleted |
| `reason` | `text` | No | - | - | Faculty justification for the deletion request |
| `status` | `enum('Pending','Approved','Rejected')` | Yes | - | Pending | Request status: 'pending', 'approved', 'rejected' |
| `request_date` | `timestamp` | No | - | current_timestamp() | Timestamp when faculty submitted the request |
| `approval_date` | `timestamp` | Yes | - | NULL | Timestamp when HOD acted upon the request |

> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `subject_id` &rarr; `subjects(id)`

### 4.4 `attendance_rules`
Regulatory attendance evaluation rules (Satisfactory, Condonation, Shortage, Detained criteria thresholds).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Rule ID |
| `reg_id` | `int(11)` | No | MUL | - | Foreign key referencing regulations.id |
| `criteria` | `enum('overall_percentage','subject_wise_percentage')` | Yes | - | overall_percentage | Attendance category ('Satisfactory', 'Condonation', 'Shortage', 'Detained') |
| `operator1` | `enum('>','>=','<','<=','==','!=')` | No | - | - | Primary comparison operator ('>=', '>', '<=', '<') |
| `value1` | `int(5)` | No | - | - | Primary percentage threshold (e.g., 75.00, 65.00) |
| `operator2` | `enum('<','<=','>','>=','==','!=')` | No | - | - | Secondary comparison operator (optional, e.g., '<', '<=') |
| `value2` | `int(5)` | No | - | - | Secondary percentage threshold (optional, e.g., 74.99) |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `reg_id` &rarr; `regulations(id)`

### 4.5 `student_permissions`
Exemption permissions granting condonation or on-duty attendance credit for institutional/medical reasons.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Permission record ID |
| `stu_id` | `int(11)` | No | MUL | - | - |
| `class_id` | `int(11)` | No | MUL | - | - |
| `date` | `date` | No | MUL | - | - |
| `hour` | `int(11)` | No | MUL | - | - |
| `permission_type` | `varchar(50)` | No | - | - | - |
| `reason` | `text` | No | - | - | Permission justification (Sports, Medical, NCC, NSS, Academic Conference) |
| `document_path` | `varchar(255)` | Yes | - | NULL | - |
| `granted_by_hod_id` | `int(11)` | No | - | - | - |
| `created_at` | `timestamp` | No | - | current_timestamp() | Submission timestamp |


## 5. Timetable Management
Hour schedules, class bell timings, effective date interval schedules, and CSV dump imports.

### 5.1 `class_timings`
Master timing templates configuring hour schedules and bell timings.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | UNI | AUTO_INCREMENT | Row identifier |
| `timing_id` | `int(11)` | No | MUL | - | Timing slot group identifier (e.g., 1, 2) |
| `hour` | `varchar(10)` | No | - | - | Period numeral (1 through 7) |
| `hour_desc` | `varchar(30)` | No | - | - | Descriptive label (e.g., I Period, II Period, Lunch Break) |
| `start_time` | `time` | No | - | - | Period start time (e.g., 09:00:00) |
| `end_time` | `time` | No | - | - | Period end time (e.g., 09:50:00) |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`id`)

### 5.2 `class_timing_schedule`
Effective date schedules binding classes to specific class timing templates over date intervals.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Schedule binding ID |
| `class_id` | `int(11)` | No | MUL | - | Foreign key referencing classes.id |
| `timing_id` | `int(11)` | No | MUL | - | Foreign key referencing class_timings.timing_id |
| `from_date` | `date` | No | MUL | - | Start date of effective timing schedule |
| `to_date` | `date` | Yes | MUL | NULL | End date of effective timing schedule |
| `created_by` | `int(11)` | Yes | - | NULL | User ID of administrator who scheduled this timing |
| `updatedat` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Foreign Keys**: `class_id` &rarr; `classes(id)`, `timing_id` &rarr; `class_timings(timing_id)`

### 5.3 `timetable_csv_dump`
Staging dump table for bulk CSV timetable imports.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `class_id` | `int(11)` | No | - | - | Target class ID in classes table |
| `weekday` | `varchar(20)` | No | - | - | - |
| `Hour` | `int(11)` | No | - | - | - |
| `subject_code` | `varchar(100)` | No | - | - | Subject code from imported timetable |
| `subject_id` | `int(11)` | Yes | MUL | NULL | - |
| `building_name` | `varchar(100)` | No | - | - | - |
| `class_hall_name` | `varchar(100)` | No | - | - | - |


## 6. Continuous Internal Assessment (CIA) & Marks
Assessment cycle headers, multi-component marks for UG, PG, Lab, and Project courses, attachment repositories, and staging tables.

### 6.1 `internal_assessments`
Continuous Internal Assessment (CIA) cycle headers configured per subject.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Assessment ID |
| `sub_id` | `int(11)` | No | MUL | - | Foreign key referencing subjects.id |
| `assessment_number` | `varchar(10)` | No | - | - | Assessment cycle number (1 = Mid-1, 2 = Mid-2) |
| `created_at` | `timestamp` | No | - | current_timestamp() | Timestamp of assessment configuration |

> **Foreign Keys**: `sub_id` &rarr; `subjects(id)`

### 6.2 `internal_assessment_marks`
UG Theory Continuous Internal Assessment (CIA) marks storage across subjective, objective, and assignment components.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle number (1 = Mid-1, 2 = Mid-2) |
| `subjective_marks` | `decimal(4,2)` | No | - | - | Subjective descriptive exam marks obtained |
| `objective_marks` | `decimal(4,2)` | No | - | - | Objective test / online quiz marks obtained |
| `assignment_marks` | `decimal(4,2)` | No | - | - | Assignment / term paper marks obtained |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`

### 6.3 `pg_internal_assessment_marks`
PG Theory Continuous Internal Assessment (CIA) marks storage.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle number (1 = Mid-1, 2 = Mid-2) |
| `marks` | `decimal(4,2)` | No | - | - | PG internal assessment marks obtained |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.4 `uglab_internal_assessment_marks`
UG Laboratory Continuous Internal Assessment marks storage.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle number (1 = Continuous Lab Evaluation) |
| `day_to_day_marks` | `decimal(4,2)` | No | - | - | Day-to-day lab performance and record marks |
| `internal_test_marks` | `decimal(4,2)` | No | - | - | End-semester internal laboratory examination marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.5 `ugproject_internal_assessment_marks`
UG Project Work Continuous Internal Assessment marks storage.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Project review cycle number (1 = Review 1, 2 = Review 2) |
| `component1_marks` | `decimal(4,2)` | No | - | - | Project presentation and defense marks |
| `component2_marks` | `decimal(4,2)` | No | - | - | Project dissertation, methodology, and progress marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.6 `cia_attachments`
Scanned answer scripts, question papers, and evaluation rubrics attached to CIA assessments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Attachment ID |
| `subject_id` | `int(11)` | No | - | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | - | - | Assessment cycle number (1 or 2) |
| `file_title` | `varchar(100)` | No | - | - | - |
| `file_path` | `varchar(255)` | No | - | - | Stored server file path in uploads directory |
| `updated_on` | `timestamp` | No | - | current_timestamp() | - |

### 6.7 `temp_internal_assessment_marks`
Staging table for bulk CSV/Excel import of UG Theory internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle (1 or 2) |
| `subjective_marks` | `varchar(5)` | Yes | - | NULL | Staged subjective marks |
| `objective_marks` | `varchar(5)` | Yes | - | NULL | Staged objective marks |
| `assignment_marks` | `varchar(5)` | Yes | - | NULL | Staged assignment marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.8 `temp_pg_internal_assessment_marks`
Staging table for bulk CSV/Excel import of PG Theory internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle (1 or 2) |
| `marks` | `varchar(5)` | Yes | - | NULL | Staged PG internal marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.9 `temp_uglab_internal_assessment_marks`
Staging table for bulk CSV/Excel import of UG Laboratory internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment cycle (1 or 2) |
| `day_to_day_marks` | `varchar(5)` | Yes | - | NULL | Staged day-to-day evaluation marks |
| `internal_test_marks` | `varchar(5)` | Yes | - | NULL | Staged internal lab test marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.10 `temp_ugproject_internal_assessment_marks`
Staging table for bulk CSV/Excel import of UG Project internal assessment marks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Staging record ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `assessment_number` | `int(11)` | No | UNI | - | Assessment review cycle (1 or 2) |
| `component1_marks` | `varchar(5)` | Yes | - | NULL | Staged component 1 marks |
| `component2_marks` | `varchar(5)` | Yes | - | NULL | Staged component 2 marks |

> **Unique Keys**: (`student_id`,`subject_id`,`assessment_number`)

### 6.11 `temp_joiningdates`
Staging table for bulk student date of joining reconciliation imports.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `sno` | `int(11)` | No | - | - | - |
| `htno` | `varchar(15)` | No | UNI | - | Student Hall Ticket Number / Roll Number |
| `name` | `varchar(100)` | No | - | - | - |
| `doj` | `varchar(30)` | No | - | - | - |
| `gender` | `varchar(10)` | No | - | - | - |

> **Unique Keys**: (`htno`)


## 7. Outcome-Based Education (OBE) & Attainment
NBA compliance framework: Course Outcomes, Program Outcomes (POs/PSOs), Blooms taxonomy tagging, and question-level marks.

### 7.1 `course_outcomes`
Course Outcomes (CO1 through CO6) formulated per subject in Outcome-Based Education (OBE).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Course Outcome ID |
| `sub_id` | `int(11)` | No | MUL | - | Foreign key referencing subjects.id |
| `co_number` | `int(11)` | No | - | - | CO sequence numeral (1 to 6) |
| `co_description` | `text` | No | - | - | Detailed learning outcome statement |

> **Foreign Keys**: `sub_id` &rarr; `subjects(id)`

### 7.2 `po_pso`
Program Outcomes (PO1 to PO12) and Program Specific Outcomes (PSO1 to PSO4) defined per regulation and specialization.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Outcome statement ID |
| `acad_year` | `varchar(20)` | No | UNI | - | Academic year string (e.g., 2024-2025) |
| `regulation` | `varchar(10)` | Yes | UNI | NULL | Regulation string (e.g., R20, R23) |
| `specid` | `int(11)` | Yes | UNI | NULL | Foreign key referencing specialization.id |
| `po_pso` | `varchar(255)` | Yes | - | NULL | Outcome type classification ('PO' or 'PSO') |
| `orderid` | `int(11)` | Yes | - | NULL | Sequential ordering index (1 to 12 for PO, 1 to 4 for PSO) |
| `code` | `varchar(10)` | Yes | UNI | NULL | Outcome code (e.g., PO1, PO2, PSO1, PSO2) |
| `description` | `varchar(500)` | Yes | - | NULL | Full text of the graduate attribute or competency statement |
| `updated_at` | `timestamp` | No | - | current_timestamp() | Last update timestamp |

> **Unique Keys**: (`acad_year`,`regulation`,`specid`,`code`)
> **Foreign Keys**: `specid` &rarr; `specialization(id)`

### 7.3 `co_po_mapping`
Course Outcome to Program Outcome / PSO articulation matrix weighting correlations.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Articulation mapping ID |
| `co_id` | `int(11)` | No | MUL | - | Foreign key referencing course_outcomes.id |
| `po_id` | `int(11)` | No | MUL | - | Foreign key referencing po_pso.id |
| `weightage` | `decimal(5,2)` | Yes | - | 1.00 | - |

> **Foreign Keys**: `co_id` &rarr; `course_outcomes(id)`, `po_id` &rarr; `po_pso(id)`

### 7.4 `blooms_levels`
Bloom's Revised Taxonomy cognitive domain levels for examination question tagging.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Bloom level ID |
| `blooms_level` | `varchar(255)` | No | - | - | Cognitive level code (L1, L2, L3, L4, L5, L6) |
| `blooms_label` | `varchar(50)` | No | - | - | Cognitive domain descriptor (Remember, Understand, Apply, Analyze, Evaluate, Create) |

### 7.5 `assessment_components`
Question paper sections or test partitions configured within an assessment cycle.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Component ID |
| `assessment_id` | `int(11)` | No | MUL | - | Foreign key referencing internal_assessments.id |
| `component_type` | `enum('Subjective','Objective','Assignment','Day-to-Day','Internal Exam','Subjective-1','Objective-1','Subjective-2','Objective-2','Activity')` | No | - | - | Component classification: 'Subjective', 'Objective', 'Assignment', 'Day-to-Day', 'Internal Exam', 'Subjective-1', 'Objective-1', 'Subjective-2', 'Objective-2', 'Activity' |
| `sequence_number` | `int(11)` | No | - | 1 | - |

> **Foreign Keys**: `assessment_id` &rarr; `internal_assessments(id)`

### 7.6 `assessment_questions`
Individual test questions tagged with maximum marks and Bloom taxonomy levels.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Question ID |
| `component_id` | `int(11)` | No | UNI | - | Foreign key referencing assessment_components.id |
| `question_label` | `varchar(10)` | No | UNI | - | Question label (e.g., Q1(a), Q1(b), Q2, Q3) |
| `question_type` | `varchar(20)` | No | - | - | - |
| `marks` | `decimal(5,2)` | No | - | - | - |
| `blooms_level_id` | `int(11)` | No | MUL | - | Foreign key referencing blooms_levels.id |

> **Unique Keys**: (`component_id`,`question_label`)
> **Foreign Keys**: `component_id` &rarr; `assessment_components(id)`, `blooms_level_id` &rarr; `blooms_levels(id)`

### 7.7 `question_co_mapping`
Maps each assessment question directly to the target Course Outcome (CO) it evaluates.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mapping ID |
| `question_id` | `int(11)` | No | MUL | - | Foreign key referencing assessment_questions.id |
| `co_id` | `int(11)` | No | MUL | - | Foreign key referencing course_outcomes.id |

> **Foreign Keys**: `question_id` &rarr; `assessment_questions(id)`, `co_id` &rarr; `course_outcomes(id)`

### 7.8 `student_marks`
Question-level marks obtained by each student used for direct CO attainment calculations.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Marks record ID |
| `stu_id` | `int(11)` | No | MUL | - | Foreign key referencing student_sub.stu_id |
| `question_id` | `int(11)` | No | MUL | - | Foreign key referencing assessment_questions.id |
| `marks_obtained` | `decimal(5,2)` | No | - | - | Numerical score awarded to the student on this question |

> **Foreign Keys**: `stu_id` &rarr; `student_sub(stu_id)`, `question_id` &rarr; `assessment_questions(id)`


## 8. Physical Infrastructure, Surveys & Student Feedback
Campus buildings, examination halls, subject questionnaires, Course Outcome indirect surveys, Course End Surveys (CES), and Faculty Appraisals.

### 8.1 `buildings`
Physical campus buildings and academic blocks.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Building ID |
| `building_name` | `varchar(100)` | No | UNI | - | Official building or academic block name |
| `status` | `tinyint(4)` | Yes | - | 1 | 1 = Active / Usable, 0 = Inactive |
| `created_at` | `timestamp` | No | - | current_timestamp() | - |
| `updated_at` | `timestamp` | No | - | current_timestamp() | - |

> **Unique Keys**: (`building_name`)

### 8.2 `halls`
Classrooms, seminar halls, and drawing halls located within buildings.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Hall ID |
| `building_id` | `int(11)` | No | UNI | - | Foreign key referencing buildings.id |
| `hall_name` | `varchar(100)` | No | UNI | - | Classroom / Hall room number or designation |
| `status` | `tinyint(4)` | Yes | - | 1 | 1 = Active / Usable, 0 = Under Maintenance / Inactive |
| `created_at` | `timestamp` | No | - | current_timestamp() | - |
| `updated_at` | `timestamp` | No | - | current_timestamp() | - |

> **Unique Keys**: (`building_id`,`hall_name`)
> **Foreign Keys**: `building_id` &rarr; `buildings(id)`

### 8.3 `subject_questionnaire_questions`
Subject feedback survey questionnaire items configured by administrators/faculty.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Question ID |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `question_number` | `int(11)` | No | UNI | - | Display ordering index (1, 2, 3...) |
| `question_text` | `text` | No | - | - | Survey question statement |
| `created_by_user_id` | `int(11)` | No | MUL | - | Foreign key referencing users.id |
| `created_by_role` | `enum('faculty','hod','admin')` | No | - | - | Role string of authoring user |
| `created_at` | `timestamp` | No | - | current_timestamp() | Timestamp of question creation |

> **Unique Keys**: (`subject_id`,`question_number`)
> **Foreign Keys**: `subject_id` &rarr; `subjects(id)`, `created_by_user_id` &rarr; `users(id)`

### 8.4 `student_questionnaire_responses`
Student responses to subject survey questions.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Response ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `question_id` | `int(11)` | No | UNI | - | Foreign key referencing subject_questionnaire_questions.id |
| `rating` | `int(11)` | No | - | - | Rating score awarded (1 to 5 Likert scale) |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Timestamp of survey submission |

> **Unique Keys**: (`student_id`,`subject_id`,`question_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`, `question_id` &rarr; `subject_questionnaire_questions(id)`

### 8.5 `student_co_feedback`
Student indirect attainment survey ratings evaluated per Course Outcome (CO).

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Feedback ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `class_id` | `int(11)` | No | MUL | - | Foreign key referencing classes.id |
| `co_id` | `int(11)` | No | UNI | - | Foreign key referencing course_outcomes.id |
| `rating` | `int(11)` | No | - | - | Student satisfaction rating (1 to 5 Likert scale) |
| `feedback_date` | `timestamp` | No | - | current_timestamp() | Timestamp of indirect CO feedback submission |

> **Unique Keys**: (`student_id`,`subject_id`,`co_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`, `class_id` &rarr; `classes(id)`, `co_id` &rarr; `course_outcomes(id)`

### 8.6 `student_ces_feedback`
Course End Survey (CES) responses evaluating curriculum, pedagogy, evaluation, learning resources, and outcome mastery across 16 questions and qualitative feedback.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Survey response ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `ces_q1` | `tinyint(2)` | No | - | - | Domain 1: Syllabus coverage and clarity (1-5 scale) |
| `ces_q2` | `tinyint(2)` | No | - | - | Domain 1: Course objectives relevance (1-5 scale) |
| `ces_q3` | `tinyint(2)` | No | - | - | Domain 1: Balance between theory and applications (1-5 scale) |
| `ces_q4` | `tinyint(2)` | No | - | - | Domain 1: Modernity and industry relevance of content (1-5 scale) |
| `ces_q5` | `tinyint(2)` | No | - | - | Domain 2: Instructor teaching effectiveness and pace (1-5 scale) |
| `ces_q6` | `tinyint(2)` | No | - | - | Domain 2: Encouragement of critical thinking and interaction (1-5 scale) |
| `ces_q7` | `tinyint(2)` | No | - | - | Domain 2: Effective use of ICT / pedagogical tools (1-5 scale) |
| `ces_q8` | `tinyint(2)` | No | - | - | Domain 2: Timely doubt clarification and mentoring (1-5 scale) |
| `ces_q9` | `tinyint(2)` | No | - | - | Domain 3: Fairness and transparency of internal evaluation (1-5 scale) |
| `ces_q10` | `tinyint(2)` | No | - | - | Domain 3: Quality and cognitive depth of test questions (1-5 scale) |
| `ces_q11` | `tinyint(2)` | No | - | - | Domain 3: Constructive feedback provided on assessments (1-5 scale) |
| `ces_q12` | `tinyint(2)` | No | - | - | Domain 4: Availability of textbooks, reference notes, and LMS material (1-5 scale) |
| `ces_q13` | `tinyint(2)` | No | - | - | Domain 4: Adequacy of lab facilities, software, or computing resources (1-5 scale) |
| `ces_q14` | `tinyint(2)` | No | - | - | Domain 4: Academic support for slow and advanced learners (1-5 scale) |
| `ces_q15` | `tinyint(2)` | No | - | - | Domain 5: Enhancement of knowledge, technical skills, and problem solving (1-5 scale) |
| `ces_q16` | `tinyint(2)` | No | - | - | Domain 5: Confidence in applying concepts to practical/professional scenarios (1-5 scale) |
| `useful_aspects` | `text` | Yes | - | NULL | Part-C Qualitative: Most useful aspects / topics of the course |
| `improvement_topics` | `text` | Yes | - | NULL | Part-C Qualitative: Topics requiring additional depth or pedagogical improvement |
| `suggestions` | `text` | Yes | - | NULL | Part-C Qualitative: General constructive suggestions for course improvement |
| `is_anonymous` | `tinyint(1)` | No | - | 0 | Anonymity flag: 0 = Identified, 1 = Anonymous submission |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Timestamp of CES survey submission |

> **Unique Keys**: (`student_id`,`subject_id`)
> **Foreign Keys**: `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`

### 8.7 `student_faculty_feedback`
Comprehensive student appraisal of teaching faculty across 19 instructional and pedagogical criteria plus qualitative comments.

| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Faculty appraisal ID |
| `student_id` | `int(11)` | No | UNI | - | Foreign key referencing students.id |
| `subject_id` | `int(11)` | No | UNI | - | Foreign key referencing subjects.id |
| `faculty_id` | `int(11)` | No | UNI | - | Foreign key referencing faculties.id |
| `fac_q1` | `tinyint(2)` | No | - | - | Criterion 1: Subject knowledge and depth of explanation (1-5 scale) |
| `fac_q2` | `tinyint(2)` | No | - | - | Criterion 2: Clarity of communication and lecture delivery (1-5 scale) |
| `fac_q3` | `tinyint(2)` | No | - | - | Criterion 3: Punctuality and regularity in conducting classes (1-5 scale) |
| `fac_q4` | `tinyint(2)` | No | - | - | Criterion 4: Syllabus completion according to academic schedule (1-5 scale) |
| `fac_q5` | `tinyint(2)` | No | - | - | Criterion 5: Blackboard / presentation organization and legibility (1-5 scale) |
| `fac_q6` | `tinyint(2)` | No | - | - | Criterion 6: Encouragement of student questions and discussions (1-5 scale) |
| `fac_q7` | `tinyint(2)` | No | - | - | Criterion 7: Ability to illustrate concepts with real-world examples (1-5 scale) |
| `fac_q8` | `tinyint(2)` | No | - | - | Criterion 8: Fairness, impartiality, and objectivity in internal grading (1-5 scale) |
| `fac_q9` | `tinyint(2)` | No | - | - | Criterion 9: Accessibility and willingness to guide outside regular hours (1-5 scale) |
| `fac_q10` | `tinyint(2)` | No | - | - | Criterion 10: Effective classroom control and discipline (1-5 scale) |
| `fac_q11` | `tinyint(2)` | No | - | - | Criterion 11: Speed of lecture and pacing adapted to student grasp (1-5 scale) |
| `fac_q12` | `tinyint(2)` | No | - | - | Criterion 12: Timely return of evaluated scripts and assignments (1-5 scale) |
| `fac_q13` | `tinyint(2)` | No | - | - | Criterion 13: Provision of supplementary learning resources and references (1-5 scale) |
| `fac_q14` | `tinyint(2)` | No | - | - | Criterion 14: Use of modern educational technology and ICT tools (1-5 scale) |
| `fac_q15` | `tinyint(2)` | No | - | - | Criterion 15: Motivation provided towards research, projects, and careers (1-5 scale) |
| `fac_q16` | `tinyint(2)` | No | - | - | Criterion 16: Respectful and approachable conduct towards students (1-5 scale) |
| `fac_q17` | `tinyint(2)` | No | - | - | Criterion 17: Attention and assistance given to struggling students (1-5 scale) |
| `fac_q18` | `tinyint(2)` | No | - | - | Criterion 18: Inspiring enthusiasm and curiosity in the subject (1-5 scale) |
| `fac_q19` | `tinyint(2)` | No | - | - | Criterion 19: Overall evaluation and instructor effectiveness (1-5 scale) |
| `faculty_strengths` | `text` | Yes | - | NULL | Qualitative: Key strengths and commendable qualities of the faculty |
| `improvement_areas` | `text` | Yes | - | NULL | Qualitative: Specific areas suggested for faculty teaching improvement |
| `additional_comments` | `text` | Yes | - | NULL | Qualitative: Additional remarks, observations, or commendations |
| `is_anonymous` | `tinyint(1)` | No | - | 1 | Anonymity flag: Default 1 (Strict student anonymity preserved for faculty appraisal) |
| `submitted_at` | `timestamp` | No | - | current_timestamp() | Timestamp of faculty feedback submission |

> **Unique Keys**: (`student_id`,`subject_id`,`faculty_id`)
> **Foreign Keys**: `faculty_id` &rarr; `faculties(id)`, `student_id` &rarr; `students(id)`, `subject_id` &rarr; `subjects(id)`

