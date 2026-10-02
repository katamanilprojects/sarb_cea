# Role-by-Role Operations Guide

This guide details the operational responsibilities, primary daily workflows, and underlying PHP model methods invoked by each user role.

---

## 1. SuperAdmin Guide

### 1.1 Overview
The SuperAdmin role is responsible for institutional master data setup, degree governance, academic policy parameters, and cohort configurations before an academic session commences.

### 1.2 Key Responsibilities & Model Methods
- **Autonomous Academic Policy Engine**: Configures regulation-specific academic settings in `superadminacademicsettings.php` via `SettingsService` (e.g., CIA theory weightages 80:20 vs 75:25, attendance detention/condonation thresholds, OBE target attainment benchmarks).
- **Academic Years**: `SuperAdmin::addAcademicYear()`, `SuperAdmin::getAcademicYears()`. Configures active terms (`2024-2025`, `2025-2026`).
- **Programs & Regulations**: Defines degrees (B.Tech, M.Tech, MCA) and autonomous academic regulations (R20, R23) via `Programs` and `Regulations` models.
- **Departments & Specializations**: Creates academic departments and branch specializations (e.g., CSE - Artificial Intelligence & Machine Learning).
- **Class Timings**: Establishes master bell schedules in `superadminclasstimings.php` with start/end times.
- **Classes & Sections**: Generates cohort sections mapped to regulation, specialization, and optional section via `SuperAdmin::generateClassName($spec_shortname, $yearsem, $section = '')` (e.g., "CSE - III-I - Sec A").
- **Admitted Student Batches**: Tracks degree cohorts across their multi-year graduation timeline in `student_batches`.
- **NBA Program Outcomes**: Uploads PO1-PO12 and PSO1-PSO4 statements into `po_pso` via `superadminpopso.php`.

---

## 2. Admin Guide

### 2.1 Overview
The Admin role handles day-to-day college-level registrar functions, student onboarding, faculty profile maintenance, and security credentials.

### 2.2 Key Responsibilities & Model Methods
- **Student Enrollment**:
  - Adds individual students or processes bulk CSV uploads (`adminenrollstudents.php`, `adminuploadst.php`).
  - Calls `Admin::addStudent($rollno, $name, $class_id, $date_of_joining)`.
  - Provisions user account in `users` with default credentials and role `'student'`.
- **Faculty Onboarding**:
  - Registers new faculty members in `adminaddfaculty.php` via `Admin::addFaculty($data)`.
  - Updates designations, departments, and active statuses in `admineditfaculty.php`.
- **Subject & Student Mapping**:
  - Maps faculty to courses (`Admin::addFacultySubjectMapping()`).
  - Maps students to subject rosters (`Admin::addStudentSubjectMapping()`).
- **Audited Password Resets**:
  - Resets forgotten student/faculty passwords with mandatory justification remarks (`Admin::resetStudentPwdWithRemarks()`, `Admin::resetFacPwdWithRemarks()`).
- **Student Feedback & Survey Oversight**:
  - Monitors college-wide feedback submission progress and response rates in `adminshowfeedbackstatus.php`.
  - Analyzes multi-level feedback reports (by Class, Subject, Faculty, and Department) in `adminviewfeedback.php` using `FeedbackService`.
  - Generates comprehensive PDF reports (`download_feedback_enhanced.php`) and Excel analytical workbooks (`download_feedback_excel.php`).

---

## 3. Academic Section Guide

### 3.1 Overview
The Academic Section role oversees examination infrastructure, centralized curriculum syllabus catalogs, and institution-wide attendance auditing.

### 3.2 Key Responsibilities & Model Methods
- **Master Curriculum Subject Catalog**:
  - Manages the master course catalog across regulations in `academicsectioncurriculumsubjects.php` using `CurriculumSubject`.
  - Defines course codes, full and short titles, weekly lecture (L), tutorial (T), practical (P) hours, and credits (C).
- **Campus Buildings & Examination Halls**:
  - Creates physical buildings and halls in `academicsectionmanagebuildings.php`.
  - Invokes `AcademicSection::getAllBuildings()`, `AcademicSection::addBuilding()`, and `AcademicSection::addHall()`.
  - Used for examination seating planning and classroom allocation.
- **Syllabus Management**:
  - Uploads official curriculum regulation PDFs and course syllabus files via `Syllabus::addSyllabus()`.
- **Emergency Password Resets**:
  - Alternative administrative office for student/faculty password resets (`AcademicSection::resetStudentPwdWithRemarks()`).
- **College-Wide Attendance Monitoring**:
  - Views attendance percentages across all departments to prepare condonation and detention lists for academic council review (`academicsectionshowallclsattendance.php`).

---

## 4. Head of Department (HOD) Guide

### 4.1 Overview
The HOD role manages departmental teaching operations, allocates subjects, coordinates weekly timetables, governs attendance deletion requests, and approves end-of-course compliance audits.

### 4.2 Key Responsibilities & Model Methods
- **Department Subject Allocation**:
  - Assigns faculty members to departmental subjects via `HOD::mapFacultyToSubject()`.
  - Subject and class offerings are queried dynamically via `Subject` model.
- **Timetable Scheduling**:
  - Builds the department's weekly timetable grid in `hodmanage_timetable.php` using `Timetable` model methods, preventing instructor conflicts.
- **Attendance Deletion Approval Gate**:
  - Reviews correction requests submitted by faculty in `hodviewdelrequests.php`.
  - Approves or rejects via `HOD::processDeleteRequest($request_id, $action)` to automatically purge mistaken records.
- **Course Delivery Compliance & Syllabus Audit Gate**:
  - Reviews faculty course completion audits in `faclessonplanreconciliation.php` or departmental audit views.
  - Reviews planned vs conducted lectures, unit milestone dates, deviations, and compensatory classes. Formally signs off for NBA Criterion 2.2 compliance.
- **Departmental Performance & CIA Review**:
  - Audits subject-wise attendance percentages (`hodshowallclsattendance.php`).
  - Reviews Mid-1 and Mid-2 internal assessment marks across all departmental classes (`hodshowcls_cia.php`).
- **Department Feedback Review**:
  - Analyzes Course Outcome (CO) feedback, Course End Survey (CES) 5-domain scores, and Faculty Appraisals across all departmental courses in `hodviewfeedback.php`.
  - Downloads official enhanced PDF reports and multi-sheet Excel workbooks for departmental NAAC/NBA documentation.

---

## 5. Faculty Guide

### 5.1 Overview
The Faculty role handles classroom-level execution: formulating lecture plans, marking daily attendance with zero friction, logging teaching topics, reconciling plans with diary entries, entering internal and external assessment marks, analyzing student learning outcomes, and reviewing student feedback for continuous pedagogical improvement.

### 5.2 Key Responsibilities & Model Methods
- **Lesson Planning**:
  - Formulates lecture delivery schedules in `facaddlessonplan.php` with unit, lecture number, planned topic, target CO, Bloom level, and pedagogy.
  - Supports bulk CSV template upload with automatic sanitization and validation (`LessonPlanService`).
- **Frictionless Daily Attendance & Teaching Diary**:
  - Marks period attendance in `facaddattendance.php` with natural free-text syllabus topic notes (zero friction, clean 4-column diary layout).
  - Unrecorded hour validation prevents accidental duplicates.
  - Subject offerings queried via `Subject` class.
  - Exceptional attendance for approved student activities via `Faculty::markExceptionalAttendance()`.
- **Attendance Correction Requests**:
  - Submits correction/deletion requests via `Faculty::submitAttendanceDeleteRequest()` when incorrect periods were entered.
- **End-of-Course Plan Reconciliation & Compliance Audit**:
  - In `faclessonplanreconciliation.php`, maps chronological teaching diary entries to planned lesson lectures using 1-Click Auto-Sequence or custom mapping.
  - Tracks unit milestone completion dates, syllabus coverage percentage, pedagogical deviations, and compensatory classes.
  - Submits formal NBA Criterion 2.2 course completion compliance audit for HOD approval.
- **CIA Marks Evaluation**:
  - Records Mid-1 and Mid-2 scores across Theory, Lab, PG, and Project courses using `CIA` and `CIAMarks` models.
  - Uploads exam attachments (question papers, answer keys) via `Faculty::addCIAAttachment()`.
- **Semester End Examination (SEE) Evaluation**:
  - Enters external marks via `facseemarks.php` (detailed question-level Mode A or direct ledger Mode B) using `SEEAssessment`.
- **Outcome-Based Education**:
  - Formulates Course Outcomes (`facaddcos.php`).
  - Fills CO-PO articulation matrices (`facarticulationmatrix.php`).
  - Maps assessment questions to COs and Bloom's taxonomy (`facquestionco.php`).
  - Generates attainment reports (`facciaanalysis2.php`).
- **Official Accreditation Reports & Dossiers**:
  - Generates complete official e-Bluebooks via `download_bluebook.php` (`EBluebookPDFService`).
  - Downloads comprehensive NBA/NAAC Course Assessment & Attainment dossiers via `download_obe_analysis.php` (`OBEAnalysisPDFService`).
- **Feedback & Continuous Improvement**:
  - Reviews indirect Course Outcome attainment ratings and 5-domain Course End Survey results in `facviewfeedback.php`.
  - Reviews student appraisal of teaching performance with strict student anonymity (`is_anonymous = 1`).
  - Inspects collapsible qualitative remarks cards (CES Part-C and Faculty Feedback) to identify curriculum and instructional enhancements.
  - Exports official PDF and Excel feedback dossiers for personal academic portfolios.

