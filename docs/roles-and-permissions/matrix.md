# Role & Permissions Matrix

This document provides a matrix of features, administrative capabilities, and page access rules across the five primary system roles.

---

## 1. Feature Access Matrix

| Feature / Capability | SuperAdmin | Admin | Academic Section | HOD | Faculty |
|---|:---:|:---:|:---:|:---:|:---:|
| **Academic Years Management** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Programs & Regulations Setup** | ✅ Full | ❌ | 👁️ Read | ❌ | ❌ |
| **Department & Specialization Setup**| ✅ Full | 👁️ Read | ❌ | ❌ | ❌ |
| **Class Timings Configuration** | ✅ Full | ❌ | ❌ | ❌ | ❌ |
| **Class Cohort Creation** | ✅ Full | 👁️ Read | ❌ | ❌ | ❌ |
| **Faculty Account Onboarding** | ❌ | ✅ Full | ❌ | 👁️ Read | ❌ |
| **Student Enrollment & Status** | ❌ | ✅ Full | 👁️ Read | 👁️ Read | ❌ |
| **Campus Buildings & Halls** | ❌ | ❌ | ✅ Full | ❌ | ❌ |
| **Syllabus Document Management** | ❌ | ❌ | ✅ Full | 👁️ Read | 👁️ Read |
| **Faculty Password Reset** | ❌ | ✅ Full | ✅ Full | ❌ | ❌ |
| **Student Password Reset** | ❌ | ✅ Full | ✅ Full | ❌ | ❌ |
| **Department Timetable Management** | ❌ | ❌ | ❌ | ✅ Full | 👁️ Read |
| **Subject Allotment to Faculty** | ❌ | ✅ Full | ❌ | ✅ Full | ❌ |
| **Student Elective/Subject Mapping**| ❌ | ✅ Full | ❌ | ✅ Full | ❌ |
| **Daily Attendance Marking & Diary**| ❌ | ❌ | ❌ | ❌ | ✅ Full |
| **Exceptional Attendance Marking** | ❌ | ❌ | ❌ | ❌ | ✅ Full |
| **Attendance Deletion Submission** | ❌ | ❌ | ❌ | ❌ | ✅ Full |
| **Attendance Deletion Approval** | ❌ | ❌ | ❌ | ✅ Full | ❌ |
| **Internal Assessment (CIA) Marks** | ❌ | ❌ | ❌ | 👁️ Read | ✅ Full |
| **CO-PO Attainment Analysis** | 👁️ Read | 👁️ Read | ❌ | 👁️ Read | ✅ Full |
| **Institutional Attendance Audits** | ✅ Full | ✅ Full | ✅ Full | 👁️ Dept | 👁️ Subj |

*Legend: ✅ Full = Create/Update/Delete; 👁️ Read = View only; ❌ = Access Denied.*

---

## 2. Page Route Authorization Mapping

### 2.1 SuperAdmin (`$_SESSION['role'] === 'superadmin'`)
- `superadminhome.php`
- `superadminacademicyears.php`
- `superadminadmins.php`
- `superadminattendancerules.php`
- `superadminclasses.php`
- `superadminclasstimings.php`
- `superadmindepts.php`
- `superadminprograms.php`
- `superadminprogramclasses.php`
- `superadminregulations.php`
- `superadminspecs.php`
- `superadminpopso.php`

### 2.2 Admin (`$_SESSION['role'] === 'admin'`)
- `adminhome.php`
- `adminaddfaculty.php`, `admineditfaculty.php`, `adminfacultyprofile.php`
- `adminenrollstudents.php`, `adminunenrollstudents.php`, `adminviewstudents.php`, `editstudent.php`
- `adminresetfacultypwd.php`, `adminresetstudentpwd.php`
- `adminshowallclsattendance.php`, `adminshowclsattendance.php`, `adminshowfacattendance.php`
- `adminciaanalysis2.php` (active choice-aware analysis; `adminciaanalysis.php` redirects here)
- `adminviewfeedback.php`

### 2.3 Academic Section (`$_SESSION['role'] === 'academic_section'`)
- `academicsectionhome.php`
- `academicsectionmanagebuildings.php`
- `academicsectionsyllabus.php`
- `academicsectionregulations.php`
- `academicsectionattendancerules.php`
- `academicsectionresetfacultypwd.php`, `academicsectionresetstudentpwd.php`
- `academicsectionshowallclsattendance.php`

### 2.4 Head of Department (`$_SESSION['role'] === 'hod'`)
- `hodhome.php`, `hod_profile.php`
- `hodmanage_timetable.php`
- `hodmapfaculty.php`, `hodmapstudents.php`
- `hodviewdelrequests.php` (Attendance deletion approvals)
- `hodviewsubjects.php`, `hodviewfaculties.php`, `hodviewclasses.php`
- `hodshowallclsattendance.php`, `hodshowclsattendance.php`, `hodshowfacattendance.php`
- `hodciaanalysis2.php` (active choice-aware analysis; `hodciaanalysis.php` redirects here)

### 2.5 Faculty (`$_SESSION['role'] === 'faculty'`)
- `fachome.php`
- `facaddattendance.php`, `facexceptionalattendance.php`, `facshowattendance.php`
- `facadddairy.php`
- `facadddelattrequest.php`, `facshowdelattrequests.php`
- `facaddciamarks.php`, `faceditciamarks.php`, `facviewciamarks.php`, `facciamarkscondensed.php`
- `facaddcialabmarks.php`, `faceditcialabmarks.php`, `facviewcialabmarks.php`, `facuglabciamarkscondensed.php`
- `facaddpgciamarks.php`, `faceditpgciamarks.php`, `facviewpgciamarks.php`
- `facaddprojectciamarks.php`, `facviewprojectciamarks.php`
- `facaddcos.php`, `facarticulationmatrix.php`, `facquestionco.php`, `facmarksentry.php`
- `facciaanalysis2.php` (active choice-aware analysis; `facciaanalysis.php` redirects here)
- `faculty_weekly_timetable.php`
