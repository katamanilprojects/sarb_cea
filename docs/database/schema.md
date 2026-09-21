# Database Schema Reference

This document provides an exhaustive, field-by-field reference of all 48 tables, columns, data types, nullability, keys, and defaults extracted directly from `u182589698_jntuaceasarb_database_scheme.sql`.

---

## 1. Identity, Access & Logging

### 1.1 `users`
Master user accounts storing credentials, contact info, and role assignment.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Unique user ID |
| `username` | `varchar(50)` | No | UNI | - | Unique login identifier (roll number, faculty username, dept code) |
| `password` | `varchar(255)` | No | | - | Hashed password (BCrypt `$2y$` or legacy MD5) |
| `name` | `varchar(100)` | No | | - | Full legal name |
| `email` | `varchar(100)` | Yes | | NULL | User contact email |
| `mobile` | `varchar(15)` | Yes | | NULL | User mobile phone number |
| `role` | `enum('superadmin','admin','academic_section','hod','faculty','student')` | No | | - | Access control role |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive / Suspended |
| `created_at` | `datetime` | Yes | | current_timestamp() | Account creation timestamp |
| `updated_at` | `datetime` | Yes | | current_timestamp() | Last modification timestamp |

### 1.2 `faculties`
Faculty member profile details.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Faculty entity ID |
| `facultyid` | `int(11)` | Yes | MUL | NULL | Foreign key referencing `users.id` |
| `username` | `varchar(50)` | No | MUL | - | References `users.username` |
| `designation` | `varchar(50)` | Yes | | NULL | Academic designation (Professor, Assistant Professor) |
| `dept_id` | `int(11)` | No | MUL | - | Foreign key referencing `departments.id` |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |

### 1.3 `students`
Student enrollment records.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Student entity ID |
| `username` | `varchar(20)` | No | MUL | - | References `users.username` (Hall Ticket / Roll number) |
| `class_id` | `int(11)` | No | MUL | - | Foreign key referencing `classes.id` |
| `status` | `int(1)` | No | | 1 | 1 = Active, 0 = Detained / Inactive |
| `date_of_joining`| `date` | Yes | | NULL | Admission date |

### 1.4 `departments`
Academic departments.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Department ID |
| `dept_shortname`| `varchar(10)` | No | | - | Short code (e.g., CSE, ECE, EEE, ME, CE) |
| `dept_fullname` | `varchar(100)` | No | | - | Full department title |
| `username` | `varchar(50)` | Yes | | NULL | HOD login username in `users` |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |

### 1.5 `activity_logs`
System-wide administrative audit trail.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Log entry ID |
| `user_id` | `int(11)` | No | MUL | - | User ID who performed the action |
| `action` | `varchar(255)` | No | | - | Category (e.g., Login, Update Password) |
| `details` | `text` | Yes | | NULL | Detailed event description |
| `timestamp` | `datetime` | Yes | | current_timestamp() | Timestamp of log event |

### 1.6 `fac_activity_logs`
Dedicated faculty activity logs (attendance, diary, marks).
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Faculty log entry ID |
| `user_id` | `int(11)` | No | MUL | - | References `faculties.id` |
| `action` | `varchar(255)` | No | | - | Action name (e.g., Attendance, Diary) |
| `details` | `text` | Yes | | NULL | Description with subject code and period |
| `timestamp` | `datetime` | Yes | | current_timestamp() | Timestamp |

---

## 2. Academic Structure

### 2.1 `academic_years`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Academic year ID |
| `acad_year` | `varchar(20)` | No | | - | Academic year label (e.g., "2024-2025") |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |

### 2.2 `programs`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Program ID |
| `program_code` | `varchar(20)` | No | | - | Degree code |
| `prog_shortname`| `varchar(20)` | No | | - | Short degree label (e.g., B.Tech, M.Tech, MCA) |
| `prog_fullname` | `varchar(100)` | No | | - | Full degree title |

### 2.3 `regulations`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Regulation ID |
| `regulation` | `varchar(20)` | No | | - | Regulation code (e.g., R15, R19, R20, R23) |
| `prog_id` | `int(11)` | No | MUL | - | References `programs.id` |

### 2.4 `specialization`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Specialization ID |
| `dept_id` | `int(11)` | No | MUL | - | References `departments.id` |
| `prog_id` | `int(11)` | No | MUL | - | References `programs.id` |
| `spec_name` | `varchar(100)` | No | | - | Full branch name |
| `spec_shortname`| `varchar(20)` | No | | - | Branch code (e.g., CSE, AIDS, ECE) |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |

### 2.5 `classes`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Class section ID |
| `classname` | `varchar(50)` | No | | - | Cohort title (e.g., "IV B.Tech I Sem CSE-A") |
| `acad_year` | `varchar(20)` | No | | - | Academic year string |
| `spec_id` | `int(11)` | No | MUL | - | References `specialization.id` |
| `reg_id` | `int(11)` | Yes | MUL | NULL | References `regulations.id` |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Archived |

### 2.6 `subjects`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Subject course ID |
| `subject_sno` | `int(11)` | Yes | | NULL | Display sequence order |
| `subcode` | `varchar(20)` | No | | - | Official course code |
| `sub_shortname`| `varchar(50)` | No | | - | Abbreviated course title |
| `sub_fullname` | `varchar(150)` | No | | - | Full official course title |
| `sub_type` | `varchar(50)` | No | | - | Type (Theory, Lab, Project, Elective) |
| `class_id` | `int(11)` | No | MUL | - | References `classes.id` |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |

---

## 3. Mapping & Junction Tables

### 3.1 `faculty_sub`
Assigns faculty members to teach subjects.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mapping record ID |
| `faculty_id` | `int(11)` | No | MUL | - | References `faculties.id` |
| `sub_id` | `int(11)` | No | MUL | - | References `subjects.id` |

### 3.2 `student_sub`
Maps enrolled students to subjects (essential for elective courses).
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mapping record ID |
| `stu_id` | `int(5)` | No | MUL | - | References `students.id` |
| `sub_id` | `int(11)` | No | MUL | - | References `subjects.id` |

---

## 4. Attendance & Classroom Operations

### 4.1 `attendance`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(5)` | No | PRI | AUTO_INCREMENT | Attendance entry ID |
| `stu_id` | `int(5)` | No | MUL | - | References `students.id` |
| `sub_id` | `int(5)` | No | MUL | - | References `subjects.id` |
| `date` | `varchar(10)` | No | | - | Date marked (YYYY-MM-DD) |
| `hour` | `int(11)` | No | | - | Period number (1 to 7) |
| `status` | `varchar(5)` | No | | - | 'P' = Present, 'A' = Absent |
| `updatedat` | `timestamp` | No | | current_timestamp() | Last modification timestamp |

### 4.2 `diary`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Diary entry ID |
| `sub_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `faculty_id` | `int(11)` | No | MUL | - | References `faculties.id` |
| `date` | `varchar(10)` | No | | - | Date of class |
| `hour` | `int(11)` | No | | - | Period hour |
| `diary` | `text` | No | | - | Topics taught / syllabus coverage |
| `createdat` | `timestamp` | No | | current_timestamp() | Timestamp |

### 4.3 `attendance_delete_requests`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Request ID |
| `faculty_id` | `int(11)` | No | MUL | - | References `faculties.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `date` | `varchar(10)` | No | | - | Class date |
| `hour` | `int(11)` | No | | - | Period hour |
| `reason` | `text` | No | | - | Justification for deletion |
| `status` | `enum('pending','approved','rejected')` | No | | 'pending' | Approval status |
| `hod_remarks`| `text` | Yes | | NULL | HOD decision notes |
| `created_at` | `timestamp` | No | | current_timestamp() | Submission timestamp |
| `updated_at` | `timestamp` | No | | current_timestamp() | Decision timestamp |

### 4.4 `attendance_rules`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Rule ID |
| `reg_id` | `int(11)` | No | MUL | - | References `regulations.id` |
| `criteria` | `varchar(50)` | No | | - | Rule category (e.g., Condonation, Satisfactory) |
| `operator1` | `varchar(10)` | No | | - | Lower boundary operator (>=, >) |
| `value1` | `decimal(5,2)` | No | | - | Primary threshold percentage |
| `operator2` | `varchar(10)` | Yes | | NULL | Upper boundary operator (<, <=) |
| `value2` | `decimal(5,2)` | Yes | | NULL | Upper boundary threshold |
| `updatedat` | `timestamp` | No | | current_timestamp() | Timestamp |

### 4.5 `student_permissions`
Approved student leave/duty permissions for institutional activities.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Permission record ID |
| `stu_id` | `int(11)` | No | MUL | - | References `students.id` |
| `class_id` | `int(11)` | No | MUL | - | References `classes.id` |
| `date` | `date` | No | | - | Date of sanctioned permission |
| `hour` | `int(11)` | No | | - | Period hour (or 0 for full day) |
| `permission_type`| `varchar(50)`| No | | - | Category (Sports, Placement, Event) |
| `reason` | `text` | No | | - | Justification |
| `document_path` | `varchar(255)`| Yes | | NULL | Uploaded sanction proof |
| `granted_by_hod_id`| `int(11)` | No | | - | Authorizing HOD |
| `created_at` | `timestamp` | No | | current_timestamp() | Timestamp |

---

## 5. Timetable Infrastructure

### 5.1 `class_timings`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `timing_id` | `int(11)` | No | PRI | AUTO_INCREMENT | Timing template ID |
| `timing_name`| `varchar(100)` | No | | - | Schedule name |
| `is_default` | `tinyint(1)` | Yes | | 0 | 1 = Default bell timing |
| `created_at` | `timestamp` | No | | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 5.2 `class_timing_schedule`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Schedule entry ID |
| `class_id` | `int(11)` | No | MUL | - | References `classes.id` |
| `timing_id` | `int(11)` | No | MUL | - | References `class_timings.timing_id` |
| `from_date` | `date` | No | | - | Validity start date |
| `to_date` | `date` | Yes | | NULL | Validity end date |
| `created_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 5.3 `timetable_csv_dump` & `timetable_csv_dump_1`
Staging tables for bulk timetable spreadsheet imports.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `class_id` | `int(11)` | No | | - | Target class ID |
| `weekday` | `varchar(20)` | No | | - | Monday .. Saturday |
| `Hour` | `int(11)` | No | | - | Period 1 .. 7 |
| `subject_code` | `varchar(100)`| No | | - | Course code in CSV |
| `subject_id` | `int(11)` | Yes | | NULL | Resolved subject ID |
| `building_name`| `varchar(100)`| No | | - | Classroom building |
| `class_hall_name`| `varchar(100)`| No | | - | Classroom room/hall name |

---

## 6. Continuous Internal Assessment (CIA) & Marks

### 6.1 `internal_assessments`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Assessment ID |
| `sub_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `assessment_number` | `int(11)` | No | | - | 1 for Mid-1, 2 for Mid-2 |
| `total_marks` | `decimal(5,2)` | No | | - | Maximum total marks |
| `assessment_date` | `date` | Yes | | NULL | Examination date |

### 6.2 `internal_assessment_marks`
UG Theory internal assessment marks.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mark entry ID |
| `student_id` | `int(5)` | No | MUL | - | References `students.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `assessment_number`| `int(11)` | No | | - | 1 or 2 |
| `subjective_marks` | `decimal(5,2)` | Yes | | NULL | Descriptive test score |
| `objective_marks` | `decimal(5,2)` | Yes | | NULL | Objective quiz score |
| `assignment_marks` | `decimal(5,2)` | Yes | | NULL | Assignment score |
| `total_marks` | `decimal(5,2)` | Yes | | NULL | Calculated total score |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 6.3 `pg_internal_assessment_marks`
Postgraduate assessment marks.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mark record ID |
| `student_id` | `int(5)` | No | MUL | - | References `students.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `assessment_number`| `int(11)` | No | | - | 1 or 2 |
| `test_marks` | `decimal(5,2)` | Yes | | NULL | Mid test score |
| `assignment_marks` | `decimal(5,2)` | Yes | | NULL | Assignment score |
| `seminar_marks` | `decimal(5,2)` | Yes | | NULL | Seminar score |
| `total_marks` | `decimal(5,2)` | Yes | | NULL | Total score |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 6.4 `uglab_internal_assessment_marks`
UG Laboratory assessment marks.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Lab mark record ID |
| `student_id` | `int(5)` | No | MUL | - | References `students.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `assessment_number`| `int(11)` | No | | - | Assessment iteration |
| `day_to_day_marks` | `decimal(5,2)` | Yes | | NULL | Continuous performance score |
| `internal_exam_marks`| `decimal(5,2)` | Yes | | NULL | Lab internal exam score |
| `total_marks` | `decimal(5,2)` | Yes | | NULL | Total score |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 6.5 `ugproject_internal_assessment_marks`
UG Final Project assessment marks.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Project mark record ID |
| `student_id` | `int(5)` | No | MUL | - | References `students.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `assessment_number`| `int(11)` | No | | - | Review phase (1, 2, 3) |
| `prj_review_marks` | `decimal(5,2)` | Yes | | NULL | Evaluation score |
| `total_marks` | `decimal(5,2)` | Yes | | NULL | Total score |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 6.6 `cia_attachments`
Digital document attachments for internal assessments (question papers, keys).
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Attachment ID |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `assessment_number`| `int(11)` | No | | - | 1 or 2 |
| `file_title` | `varchar(100)` | No | | - | Document label |
| `file_path` | `varchar(255)` | No | | - | Stored file path in `uploads/` |
| `updated_on` | `timestamp` | No | | current_timestamp() | Timestamp |

### 6.7 Staging Tables for Marks Imports
- **`temp_internal_assessment_marks`**: Staging for theory marks CSV upload (`student_id`, `subject_id`, `assessment_number`, `subjective_marks`, `objective_marks`, `assignment_marks`).
- **`temp_pg_internal_assessment_marks`**: Staging for PG marks (`student_id`, `subject_id`, `assessment_number`, `marks`).
- **`temp_uglab_internal_assessment_marks`**: Staging for Lab marks (`student_id`, `subject_id`, `assessment_number`, `day_to_day_marks`, `internal_test_marks`).
- **`temp_ugproject_internal_assessment_marks`**: Staging for Project marks (`student_id`, `subject_id`, `assessment_number`, `component1_marks`, `component2_marks`).
- **`temp_joiningdates`**: Staging table for student admission dates (`htno`, `name`, `doj`, `gender`).

---

## 7. Outcome-Based Education (OBE) & Attainment

### 7.1 `course_outcomes`
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Course Outcome ID |
| `sub_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `co_code` | `varchar(10)` | No | | - | Outcome code (CO1, CO2, etc.) |
| `co_statement` | `text` | No | | - | Description of learning objective |
| `created_at` | `timestamp` | No | | current_timestamp() | Creation timestamp |

### 7.2 `po_pso` & `po_pso1`
Program Outcomes and Program Specific Outcomes.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Outcome ID |
| `acad_year` | `varchar(20)` | No | | - | Academic year |
| `regulation` | `varchar(20)` | No | | - | Regulation string |
| `specid` | `int(11)` | No | MUL | - | References `specialization.id` |
| `type` | `enum('PO','PSO')` | No | | - | Type identifier |
| `code` | `varchar(10)` | No | | - | PO1..PO12, PSO1..PSO4 |
| `statement` | `text` | No | | - | Outcome description |

### 7.3 `co_po_mapping`
CO to PO/PSO articulation matrix.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mapping record ID |
| `co_id` | `int(11)` | No | MUL | - | References `course_outcomes.id` |
| `po_id` | `int(11)` | No | MUL | - | References `po_pso.id` |
| `weightage` | `decimal(5,2)` | Yes | | 1.00 | Correlation level (1, 2, or 3) |

### 7.4 `blooms_levels`
Cognitive domain levels.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Level ID |
| `level_code` | `varchar(10)` | No | | - | L1 .. L6 |
| `level_name` | `varchar(50)` | No | | - | Remember, Understand, Apply, Analyze, Evaluate, Create |
| `description`| `text` | Yes | | NULL | Cognitive expectation |

### 7.5 `assessment_components`
Assessment components for attainment analysis.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Component ID |
| `assessment_id` | `int(11)` | No | MUL | - | References `internal_assessments.id` |
| `component_type`| `enum(...)` | No | | - | 'Subjective','Objective','Assignment','Day-to-Day', etc. |
| `sequence_number`| `int(11)` | No | | 1 | Display sequence |

### 7.6 `assessment_questions`
Question-level breakdown for an assessment component.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Question ID |
| `component_id` | `int(11)` | No | MUL | - | References `assessment_components.id` |
| `question_label`| `varchar(10)` | No | | - | Question label (e.g., Q1a, Q1b, Q2) |
| `question_type` | `varchar(20)` | No | | - | Question format type |
| `marks` | `decimal(5,2)`| No | | - | Maximum score for question |
| `blooms_level_id`| `int(11)` | No | MUL | - | References `blooms_levels.id` |

### 7.7 `question_co_mapping`
Maps assessment questions to Course Outcomes.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Mapping ID |
| `question_id` | `int(11)` | No | MUL | - | References `assessment_questions.id` |
| `co_id` | `int(11)` | No | MUL | - | References `course_outcomes.id` |

### 7.8 `student_marks`
Stores student marks obtained for individual assessment questions.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Score entry ID |
| `stu_id` | `int(5)` | No | MUL | - | References `student_sub.stu_id` |
| `question_id` | `int(11)` | No | MUL | - | References `assessment_questions.id` |
| `marks_obtained`| `decimal(5,2)`| No | | - | Actual marks earned |

---

## 8. Physical Infrastructure & Feedback

### 8.1 `buildings`
Campus facilities managed by the Academic Section.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Building ID |
| `building_name` | `varchar(100)` | No | | - | Building title (e.g., Mechanical Block, Main Building) |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |
| `created_at` | `timestamp` | No | | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 8.2 `halls`
Classrooms and examination halls within buildings.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Hall ID |
| `building_id` | `int(11)` | No | MUL | - | References `buildings.id` (ON DELETE CASCADE) |
| `hall_name` | `varchar(100)` | No | | - | Hall identifier (e.g., Room 204, Seminar Hall 1) |
| `status` | `int(11)` | No | | 1 | 1 = Active, 0 = Inactive |
| `created_at` | `timestamp` | No | | current_timestamp() | Creation timestamp |
| `updated_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 8.3 `subject_questionnaire_questions`
Survey feedback questions configured per subject.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Question ID |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` (ON DELETE CASCADE) |
| `question_number`| `int(11)` | No | | - | Display order |
| `question_text` | `text` | No | | - | Survey prompt text |
| `created_by_user_id`| `int(11)`| No | MUL | - | References `users.id` |
| `created_by_role`| `varchar(50)`| No | | - | Creator role |
| `created_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 8.4 `student_questionnaire_responses`
Student responses to subject survey questions.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Response ID |
| `student_id` | `int(5)` | No | MUL | - | References `students.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `question_id` | `int(11)` | No | MUL | - | References `subject_questionnaire_questions.id` |
| `rating` | `int(11)` | No | | - | Numerical rating score (1 to 5) |
| `created_at` | `timestamp` | No | | current_timestamp() | Timestamp |

### 8.5 `student_co_feedback`
Student indirect survey ratings on mastery of individual Course Outcomes.
| Column | Type | Nullable | Key | Default | Description |
|---|---|---|---|---|---|
| `id` | `int(11)` | No | PRI | AUTO_INCREMENT | Feedback ID |
| `student_id` | `int(5)` | No | MUL | - | References `students.id` |
| `subject_id` | `int(11)` | No | MUL | - | References `subjects.id` |
| `class_id` | `int(11)` | No | MUL | - | References `classes.id` |
| `co_id` | `int(11)` | No | MUL | - | References `course_outcomes.id` |
| `rating` | `int(11)` | No | | - | Satisfaction rating (1 to 5) |
| `created_at` | `timestamp` | No | | current_timestamp() | Timestamp |
