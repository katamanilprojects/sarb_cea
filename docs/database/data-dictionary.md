# Database Data Dictionary

This document details enumeration values, status flag semantics, criteria operators, and standardized code representations used across the database.

---

## 1. System Roles (`users.role`)

The `role` column in the `users` table defines access levels and interface routing:

| Role String | Description | Default Landing Page |
|---|---|---|
| `superadmin` | Institutional superuser; full control over academic years, programs, regulations, specs, and classes. | `superadminhome.php` |
| `admin` | Administrative officer; student enrollment, faculty profiles, subject mappings, password management. | `adminhome.php` |
| `academic_section` | Academic section affairs; infrastructure (buildings, halls), regulations, syllabus, institutional attendance audits. | `academicsectionhome.php` |
| `hod` | Head of Department; timetable management, departmental subjects, faculty assignment, student mapping, attendance deletion review. | `hodhome.php` |
| `faculty` | Teaching faculty; daily attendance, diary entries, internal assessment marks entry, deletion requests. | `fachome.php` |
| `student` | Student portal actor (read-only views of attendance and CIA marks). | `studenthome.php` |

---

## 2. Status Flags (`status`)

Most master tables include a `status` integer flag used for soft-deactivation:

| Table | Value | Meaning |
|---|---|---|
| `users` | `1` | **Active**: Allowed to log in. |
| `users` | `0` | **Inactive / Suspended**: Login rejected by `User::authenticate()`. |
| `faculties` | `1` | **Active**: Eligible for subject allotment and timetable scheduling. |
| `faculties` | `0` | **Relieved / Inactive**: Excluded from active faculty dropdowns. |
| `students` | `1` | **Active**: Enrolled and eligible for attendance and marks entry. |
| `students` | `0` | **Detained / Left**: Excluded from daily attendance rolls. |
| `departments` | `1` | **Active Department**. |
| `departments` | `0` | **Archived / Inactive**. |
| `specialization`| `1` | **Active Specialization Branch**. |
| `specialization`| `0` | **Discontinued Branch**. |
| `classes` | `1` | **Current Academic Class**: Displayed in active menus and schedules. |
| `classes` | `0` | **Completed / Archived Cohort**. |
| `subjects` | `1` | **Active Course**: Taught in the current term. |
| `subjects` | `0` | **Archived Course**. |
| `buildings` | `1` | **Active Facility**. |
| `buildings` | `0` | **Under Renovation / Inactive**. |
| `halls` | `1` | **Usable Hall / Classroom**. |
| `halls` | `0` | **Temporarily Unavailable**. |

---

## 3. Attendance Constants

### 3.1 Attendance Status (`attendance.status`)
- `'P'`: **Present**. Counted towards attended hours.
- `'A'`: **Absent**. Counted towards conducted hours, but not attended.

### 3.2 Period / Hour (`attendance.hour`, `diary.hour`)
Integer value ranging from `1` through `7`, corresponding to the periods configured in `class_timings` for that date.

### 3.3 Attendance Deletion Status (`attendance_delete_requests.status`)
- `'pending'`: Awaiting review by the Department HOD.
- `'approved'`: Approved by HOD; matching records in `attendance` and `diary` have been purged.
- `'rejected'`: Rejected by HOD with comments recorded in `hod_remarks`.

---

## 4. Assessment Component Types (`assessment_components.component_type`)

ENUM values defining the structure of internal assessments:

| Component Type | Description |
|---|---|
| `Subjective` | Traditional descriptive examination (descriptive written answers). |
| `Objective` | Multiple choice, online quiz, or short-answer tests. |
| `Assignment` | Take-home assignments, term papers, or case study evaluations. |
| `Day-to-Day` | Continuous laboratory performance, observation, and record maintenance. |
| `Internal Exam` | Formal end-semester internal lab exam or viva voce. |
| `Subjective-1` | Mid-Term 1 descriptive test component. |
| `Objective-1` | Mid-Term 1 objective test component. |
| `Subjective-2` | Mid-Term 2 descriptive test component. |
| `Objective-2` | Mid-Term 2 objective test component. |
| `Activity` | Co-curricular, seminar, or mini-project assessment activity. |

---

## 5. Bloom's Taxonomy Cognitive Levels (`blooms_levels`)

Used in NBA/OBE question tagging and student marks attainment analysis:

| Level Code | Level Name | Cognitive Domain Target |
|---|---|---|
| `L1` | Remember | Recalling facts, terms, and basic concepts. |
| `L2` | Understand | Demonstrating comprehension of ideas and principles. |
| `L3` | Apply | Solving problems by applying acquired knowledge in new situations. |
| `L4` | Analyze | Examining and breaking information into parts to explore relationships. |
| `L5` | Evaluate | Justifying a stance, decision, or assessing work based on criteria. |
| `L6` | Create | Synthesizing elements into a new pattern, design, or original solution. |

---

## 6. Outcome-Based Education (OBE) Classifications

### 6.1 `po_pso.type`
- `'PO'`: **Program Outcome**. Twelve standard graduate attributes mandated by the National Board of Accreditation (NBA) (PO1 through PO12).
- `'PSO'`: **Program Specific Outcome**. Department-specific graduate competencies (PSO1 through PSO4).

### 6.2 Correlation Levels (`co_po_mapping.correlation_level`)
Numeric weighting indicating how strongly a Course Outcome addresses a Program Outcome:
- `1`: **Slight (Low)** correlation.
- `2`: **Moderate (Medium)** correlation.
- `3`: **Substantial (High)** correlation.
- `NULL` / `0`: No correlation.

---

## 7. Attendance Rule Operators (`attendance_rules`)

Configures regulatory percentage evaluation:

| Field | Typical Values | Meaning |
|---|---|---|
| `criteria` | 'Satisfactory', 'Condonation', 'Shortage', 'Detained' | Evaluation status category |
| `operator1` | `>=`, `>`, `<=`, `<` | Primary condition operator applied against `value1` |
| `value1` | `75.00`, `65.00`, `0.00` | Lower or primary percentage boundary |
| `operator2` | `<`, `<=`, `NULL` | Optional upper boundary operator applied against `value2` |
| `value2` | `74.99`, `64.99`, `NULL` | Optional upper boundary percentage |

*Example Rule*: Condonation range is defined as `operator1 = '>='`, `value1 = 65.00`, `operator2 = '<'`, `value2 = 75.00`.
