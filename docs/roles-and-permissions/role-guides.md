# Role-by-Role Operations Guide

This guide details the operational responsibilities, primary daily workflows, and underlying PHP model methods invoked by each user role.

---

## 1. SuperAdmin Guide

### 1.1 Overview
The SuperAdmin role is responsible for institutional master data setup before an academic session commences.

### 1.2 Key Responsibilities & Model Methods
- **Academic Years**: `SuperAdmin::addAcademicYear()`, `SuperAdmin::getAcademicYears()`. Configures active terms (`2024-2025`, `2025-2026`).
- **Programs & Regulations**: Defines degrees (B.Tech, M.Tech, MCA) and autonomous academic regulations (R20, R23) via `Programs` and `Regulations` models.
- **Departments & Specializations**: Creates academic departments and branch specializations (e.g., CSE - Artificial Intelligence & Machine Learning).
- **Class Timings**: Establishes master bell schedules in `superadminclasstimings.php` with start/end times.
- **Classes**: Generates cohort sections (e.g., "III B.Tech II Sem ECE-A") mapped to regulation and specialization.
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

---

## 3. Academic Section Guide

### 3.1 Overview
The Academic Section role oversees examination infrastructure, curriculum syllabus repositories, and institution-wide attendance auditing.

### 3.2 Key Responsibilities & Model Methods
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
The HOD role manages departmental teaching operations, allocates subjects, coordinates weekly timetables, and governs attendance deletion requests.

### 4.2 Key Responsibilities & Model Methods
- **Department Subject Allocation**:
  - Assigns faculty members to departmental subjects via `HOD::mapFacultyToSubject()`.
- **Timetable Scheduling**:
  - Builds the department's weekly timetable grid in `hodmanage_timetable.php` using `Timetable` model methods.
- **Attendance Deletion Approval Gate**:
  - Reviews correction requests submitted by faculty in `hodviewdelrequests.php`.
  - Approves or rejects via `HOD::processDeleteRequest($request_id, $action)` to automatically purge mistaken records.
- **Departmental Performance & CIA Review**:
  - Audits subject-wise attendance percentages (`hodshowallclsattendance.php`).
  - Reviews Mid-1 and Mid-2 internal assessment marks across all departmental classes (`hodshowcls_cia.php`).

---

## 5. Faculty Guide

### 5.1 Overview
The Faculty role handles classroom-level execution: marking attendance, logging teaching topics, entering internal marks, and analyzing student learning outcomes.

### 5.2 Key Responsibilities & Model Methods
- **Attendance & Diary**:
  - Marks period attendance with absent checklist and enters syllabus topic diary via `Faculty::markAttendance()`.
  - Exceptional attendance for approved student activities via `Faculty::markExceptionalAttendance()`.
- **Attendance Correction Requests**:
  - Submits correction/deletion requests via `Faculty::submitAttendanceDeleteRequest()` when incorrect periods were entered.
- **CIA Marks Evaluation**:
  - Records Mid-1 and Mid-2 scores across Theory, Lab, PG, and Project courses using `CIA` model methods.
  - Uploads exam attachments (question papers, answer keys) via `Faculty::addCIAAttachment()`.
- **Outcome-Based Education**:
  - Formulates Course Outcomes (`facaddcos.php`).
  - Fills CO-PO articulation matrices (`facarticulationmatrix.php`).
  - Maps assessment questions to COs and Bloom's taxonomy (`facquestionco.php`).
  - Generates attainment reports (`facciaanalysis.php`).
