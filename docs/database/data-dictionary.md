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
| `curriculum_subjects` | `1` | **Active Master Subject**: Visible in curriculum syllabus catalogs and class allotment picker. |
| `curriculum_subjects` | `0` | **Archived / Inactive Master Subject**. |
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

---

## 8. Student Surveys & Institutional Feedback

### 8.1 5-Point Likert Rating Scale
Standardized rating score used across `student_co_feedback`, `student_ces_feedback`, `student_faculty_feedback`, and `student_questionnaire_responses`:

| Score | Descriptive Label | Indirect CO Attainment Meaning | Faculty Appraisal Meaning |
|---|---|---|---|
| `5` | Strongly Agree / Excellent | Complete mastery of outcome | Outstanding teaching effectiveness |
| `4` | Agree / Very Good | Proficient mastery of outcome | Commendable pedagogical delivery |
| `3` | Neutral / Good | Adequate competency (Threshold) | Satisfactory classroom instruction |
| `2` | Disagree / Fair | Incomplete understanding | Needs pedagogical improvement |
| `1` | Strongly Disagree / Poor | Deficient understanding | Unsatisfactory instruction |

### 8.2 Course Outcome (CO) Attainment Thresholds (`student_co_feedback`)
- **Target Attainment Benchmark**: $\ge 3.0$ on a 5.0 scale (or $\ge 60\%$) signifies that a student has met the learning outcome threshold.
- **Indirect Attainment Level Calculation**:
  - **Level 3**: $\ge 70\%$ of participating students rated $\ge 3.0$.
  - **Level 2**: $60\% - 69\%$ of participating students rated $\ge 3.0$.
  - **Level 1**: $50\% - 59\%$ of participating students rated $\ge 3.0$.
  - **Level 0**: $< 50\%$ of participating students rated $\ge 3.0$.

### 8.3 Course End Survey (CES) 5-Domain Evaluation (`student_ces_feedback`)
The 16 questions (`ces_q1` through `ces_q16`) map into five core NAAC/NBA quality domains:

| Domain Index | Domain Title | Covered Survey Items | Focus Area |
|---|---|---|---|
| **Domain 1** | Curriculum & Syllabus Design | `ces_q1` &ndash; `ces_q4` | Clarity, relevance, theory-application balance, and industry modernization |
| **Domain 2** | Pedagogy & Teaching-Learning Process | `ces_q5` &ndash; `ces_q8` | Instructional pace, interactive discussion, ICT tools, and doubt clarification |
| **Domain 3** | Continuous Assessment & Evaluation | `ces_q9` &ndash; `ces_q11` | Grading fairness, cognitive rigor of exam questions, and feedback timeliness |
| **Domain 4** | Learning Resources & Academic Support | `ces_q12` &ndash; `ces_q14` | Library/LMS resources, computing/lab facilities, and remedial support |
| **Domain 5** | Outcome Attainment & Competency | `ces_q15` &ndash; `ces_q16` | Problem-solving skills, practical confidence, and professional growth |

#### CES Qualitative Fields (Part-C):
- `useful_aspects`: Most beneficial and intellectually stimulating topics or components of the course.
- `improvement_topics`: Topics that students feel require greater depth, alternative teaching methods, or curriculum adjustment.
- `suggestions`: General constructive recommendations for syllabus revision or laboratory alignment.

### 8.4 Student Faculty Appraisal (`student_faculty_feedback`)
Evaluates instructor performance across 19 instructional dimensions:
- `fac_q1` to `fac_q5`: Subject expertise, lecture clarity, punctuality, syllabus schedule adherence, and presentation clarity.
- `fac_q6` to `fac_q10`: Interactive encouragement, real-world examples, grading impartiality, outside-hours mentoring, and classroom control.
- `fac_q11` to `fac_q15`: Lecture pacing, test script return timeliness, supplementary notes, ICT utilization, and career motivation.
- `fac_q16` to `fac_q19`: Approachability, support for struggling students, inspiring student interest, and overall effectiveness.

#### Faculty Appraisal Qualitative Fields:
- `faculty_strengths`: Key teacher qualities, clarity, and pedagogical strengths appreciated by students.
- `improvement_areas`: Specific constructive feedback for instructional improvement.
- `additional_comments`: Unstructured student feedback or commendations.

### 8.5 Anonymity Enforcement (`is_anonymous`)
- **`student_ces_feedback.is_anonymous`**: Defaults to `0` (identified by default, optional student anonymity).
- **`student_faculty_feedback.is_anonymous`**: Defaults to `1` (strict student anonymity). When set to `1`:
  - Student roll numbers and names are completely suppressed from faculty, HOD, and administrative views.
  - PDF reports and Excel exports mask student identities as "Anonymous Student" or aggregate metrics only.
  - Ensures unbiased, honest feedback without fear of academic retaliation.

---

## 9. Continuous Internal Assessment (CIA) Marks Schemas

Different degree levels and subject types store marks in dedicated specialized columns:

| Subject Type | Target Marks Table | Key Columns | Maximum Typical Marks |
|---|---|---|---|
| **UG Theory** | `internal_assessment_marks` | `subjective_marks`, `objective_marks`, `assignment_marks` | 30 marks (e.g., 20 subjective + 10 objective/assignment) |
| **PG Theory** | `pg_internal_assessment_marks` | `marks` | 40 marks |
| **UG Laboratory** | `uglab_internal_assessment_marks` | `day_to_day_marks`, `internal_test_marks` | 30 marks (e.g., 20 day-to-day + 10 lab test) |
| **UG Project** | `ugproject_internal_assessment_marks` | `component1_marks`, `component2_marks` | 50 / 100 marks (Review 1 & Review 2 defenses) |

