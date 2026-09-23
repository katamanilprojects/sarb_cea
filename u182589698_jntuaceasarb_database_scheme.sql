-- phpMyAdmin SQL Dump
-- version 5.2.2
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 09:20 AM
-- Server version: 11.8.9-MariaDB-log
-- PHP Version: 7.2.34

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `u182589698_jntuaceasarb`
--

-- --------------------------------------------------------

--
-- Table structure for table `academic_years`
--

CREATE TABLE `academic_years` (
  `id` int(11) NOT NULL,
  `acad_year` varchar(20) NOT NULL,
  `status` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_components`
--

CREATE TABLE `assessment_components` (
  `id` int(11) NOT NULL,
  `assessment_id` int(11) NOT NULL,
  `component_type` enum('Subjective','Objective','Assignment','Day-to-Day','Internal Exam','Subjective-1','Objective-1','Subjective-2','Objective-2','Activity') NOT NULL,
  `sequence_number` int(11) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `assessment_questions`
--

CREATE TABLE `assessment_questions` (
  `id` int(11) NOT NULL,
  `component_id` int(11) NOT NULL,
  `question_label` varchar(10) NOT NULL,
  `question_type` varchar(20) NOT NULL,
  `marks` decimal(5,2) NOT NULL,
  `blooms_level_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(5) NOT NULL,
  `stu_id` int(5) NOT NULL,
  `sub_id` int(5) NOT NULL,
  `date` varchar(10) NOT NULL,
  `hour` int(11) NOT NULL,
  `status` varchar(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_delete_requests`
--

CREATE TABLE `attendance_delete_requests` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `hour` int(11) NOT NULL,
  `reason` text NOT NULL,
  `status` enum('Pending','Approved','Rejected') DEFAULT 'Pending',
  `request_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `approval_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_rules`
--

CREATE TABLE `attendance_rules` (
  `id` int(11) NOT NULL,
  `reg_id` int(11) NOT NULL,
  `criteria` enum('overall_percentage','subject_wise_percentage') DEFAULT 'overall_percentage',
  `operator1` enum('>','>=','<','<=','==','!=') NOT NULL,
  `value1` int(5) NOT NULL,
  `operator2` enum('<','<=','>','>=','==','!=') NOT NULL,
  `value2` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `blooms_levels`
--

CREATE TABLE `blooms_levels` (
  `id` int(11) NOT NULL,
  `blooms_level` varchar(255) NOT NULL,
  `blooms_label` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `buildings`
--

CREATE TABLE `buildings` (
  `id` int(11) NOT NULL,
  `building_name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cia_attachments`
--

CREATE TABLE `cia_attachments` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `file_title` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `updated_on` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `classes`
--

CREATE TABLE `classes` (
  `id` int(5) NOT NULL,
  `acad_year` varchar(10) NOT NULL,
  `classname` varchar(60) NOT NULL,
  `yearsem` varchar(20) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `spec_id` int(5) NOT NULL,
  `timing_id` int(11) NOT NULL DEFAULT 1,
  `reg` varchar(10) NOT NULL,
  `reg_id` int(11) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_timings`
--

CREATE TABLE `class_timings` (
  `id` int(11) NOT NULL,
  `timing_id` int(11) NOT NULL,
  `hour` varchar(10) NOT NULL,
  `hour_desc` varchar(30) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `class_timing_schedule`
--

CREATE TABLE `class_timing_schedule` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `timing_id` int(11) NOT NULL,
  `from_date` date NOT NULL,
  `to_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `course_outcomes`
--

CREATE TABLE `course_outcomes` (
  `id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `co_number` int(11) NOT NULL,
  `co_description` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `co_po_mapping`
--

CREATE TABLE `co_po_mapping` (
  `id` int(11) NOT NULL,
  `co_id` int(11) NOT NULL,
  `po_id` int(11) NOT NULL,
  `weightage` decimal(5,2) DEFAULT 1.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `dept_shortname` varchar(20) NOT NULL,
  `dept_fullname` varchar(100) NOT NULL,
  `status` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `diary`
--

CREATE TABLE `diary` (
  `id` int(5) NOT NULL,
  `sub_id` int(5) NOT NULL,
  `faculty_id` int(5) NOT NULL,
  `date` varchar(10) NOT NULL,
  `hour` int(11) NOT NULL,
  `diary` varchar(255) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculties`
--

CREATE TABLE `faculties` (
  `id` int(11) NOT NULL,
  `username` varchar(30) NOT NULL,
  `designation` varchar(30) NOT NULL,
  `facultyid` int(5) NOT NULL,
  `dept_id` int(11) NOT NULL,
  `status` varchar(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `faculty_sub`
--

CREATE TABLE `faculty_sub` (
  `id` int(5) NOT NULL,
  `faculty_id` int(5) NOT NULL,
  `sub_id` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `fac_activity_logs`
--

CREATE TABLE `fac_activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(255) NOT NULL,
  `details` text DEFAULT NULL,
  `timestamp` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `halls`
--

CREATE TABLE `halls` (
  `id` int(11) NOT NULL,
  `building_id` int(11) NOT NULL,
  `hall_name` varchar(100) NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internal_assessments`
--

CREATE TABLE `internal_assessments` (
  `id` int(11) NOT NULL,
  `sub_id` int(11) NOT NULL,
  `assessment_number` varchar(10) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `internal_assessment_marks`
--

CREATE TABLE `internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `subjective_marks` decimal(4,2) NOT NULL,
  `objective_marks` decimal(4,2) NOT NULL,
  `assignment_marks` decimal(4,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `pg_internal_assessment_marks`
--

CREATE TABLE `pg_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `marks` decimal(4,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `po_pso`
--

CREATE TABLE `po_pso` (
  `id` int(11) NOT NULL,
  `acad_year` varchar(20) NOT NULL,
  `regulation` varchar(10) DEFAULT NULL,
  `specid` int(11) DEFAULT NULL,
  `po_pso` varchar(255) DEFAULT NULL,
  `orderid` int(11) DEFAULT NULL,
  `code` varchar(10) DEFAULT NULL,
  `description` varchar(500) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `programs`
--

CREATE TABLE `programs` (
  `id` int(5) NOT NULL,
  `program_code` varchar(5) NOT NULL,
  `prog_shortname` varchar(20) NOT NULL,
  `prog_fullname` varchar(50) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `question_co_mapping`
--

CREATE TABLE `question_co_mapping` (
  `id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `co_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `regulations`
--

CREATE TABLE `regulations` (
  `id` int(11) NOT NULL,
  `regulation` varchar(4) NOT NULL,
  `prog_id` int(11) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `specialization`
--

CREATE TABLE `specialization` (
  `id` int(5) NOT NULL,
  `spec_code` varchar(20) NOT NULL,
  `spec_shortname` varchar(50) NOT NULL,
  `spec_fullname` varchar(100) NOT NULL,
  `dept_id` int(5) NOT NULL,
  `prog_id` int(5) NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0=Inactive, 1=Active',
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `students`
--

CREATE TABLE `students` (
  `id` int(5) NOT NULL,
  `username` varchar(20) NOT NULL,
  `class_id` int(5) NOT NULL,
  `status` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `date_of_joining` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_ces_feedback`
--

CREATE TABLE `student_ces_feedback` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `ces_q1` tinyint(2) NOT NULL,
  `ces_q2` tinyint(2) NOT NULL,
  `ces_q3` tinyint(2) NOT NULL,
  `ces_q4` tinyint(2) NOT NULL,
  `ces_q5` tinyint(2) NOT NULL,
  `ces_q6` tinyint(2) NOT NULL,
  `ces_q7` tinyint(2) NOT NULL,
  `ces_q8` tinyint(2) NOT NULL,
  `ces_q9` tinyint(2) NOT NULL,
  `ces_q10` tinyint(2) NOT NULL,
  `ces_q11` tinyint(2) NOT NULL,
  `ces_q12` tinyint(2) NOT NULL,
  `ces_q13` tinyint(2) NOT NULL,
  `ces_q14` tinyint(2) NOT NULL,
  `ces_q15` tinyint(2) NOT NULL,
  `ces_q16` tinyint(2) NOT NULL,
  `useful_aspects` text DEFAULT NULL,
  `improvement_topics` text DEFAULT NULL,
  `suggestions` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_co_feedback`
--

CREATE TABLE `student_co_feedback` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `co_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `feedback_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_faculty_feedback`
--

CREATE TABLE `student_faculty_feedback` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `fac_q1` tinyint(2) NOT NULL,
  `fac_q2` tinyint(2) NOT NULL,
  `fac_q3` tinyint(2) NOT NULL,
  `fac_q4` tinyint(2) NOT NULL,
  `fac_q5` tinyint(2) NOT NULL,
  `fac_q6` tinyint(2) NOT NULL,
  `fac_q7` tinyint(2) NOT NULL,
  `fac_q8` tinyint(2) NOT NULL,
  `fac_q9` tinyint(2) NOT NULL,
  `fac_q10` tinyint(2) NOT NULL,
  `fac_q11` tinyint(2) NOT NULL,
  `fac_q12` tinyint(2) NOT NULL,
  `fac_q13` tinyint(2) NOT NULL,
  `fac_q14` tinyint(2) NOT NULL,
  `fac_q15` tinyint(2) NOT NULL,
  `fac_q16` tinyint(2) NOT NULL,
  `fac_q17` tinyint(2) NOT NULL,
  `fac_q18` tinyint(2) NOT NULL,
  `fac_q19` tinyint(2) NOT NULL,
  `faculty_strengths` text DEFAULT NULL,
  `improvement_areas` text DEFAULT NULL,
  `additional_comments` text DEFAULT NULL,
  `is_anonymous` tinyint(1) NOT NULL DEFAULT 0,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_marks`
--

CREATE TABLE `student_marks` (
  `id` int(11) NOT NULL,
  `stu_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `marks_obtained` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_permissions`
--

CREATE TABLE `student_permissions` (
  `id` int(11) NOT NULL,
  `stu_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `hour` int(11) NOT NULL,
  `permission_type` varchar(50) NOT NULL,
  `reason` text NOT NULL,
  `document_path` varchar(255) DEFAULT NULL,
  `granted_by_hod_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_questionnaire_responses`
--

CREATE TABLE `student_questionnaire_responses` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `student_sub`
--

CREATE TABLE `student_sub` (
  `id` int(5) NOT NULL,
  `stu_id` int(5) NOT NULL,
  `sub_id` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subjects`
--

CREATE TABLE `subjects` (
  `id` int(5) NOT NULL,
  `subject_sno` varchar(3) NOT NULL,
  `subcode` varchar(20) NOT NULL,
  `sub_shortname` varchar(20) NOT NULL,
  `sub_fullname` varchar(100) NOT NULL,
  `sub_type` varchar(20) NOT NULL,
  `class_id` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `subject_questionnaire_questions`
--

CREATE TABLE `subject_questionnaire_questions` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `question_number` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_by_role` enum('faculty','hod','admin') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_internal_assessment_marks`
--

CREATE TABLE `temp_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `subjective_marks` varchar(5) DEFAULT NULL,
  `objective_marks` varchar(5) DEFAULT NULL,
  `assignment_marks` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_joiningdates`
--

CREATE TABLE `temp_joiningdates` (
  `sno` int(11) NOT NULL,
  `htno` varchar(15) NOT NULL,
  `name` varchar(100) NOT NULL,
  `doj` varchar(30) NOT NULL,
  `gender` varchar(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_pg_internal_assessment_marks`
--

CREATE TABLE `temp_pg_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `marks` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_uglab_internal_assessment_marks`
--

CREATE TABLE `temp_uglab_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `day_to_day_marks` varchar(5) DEFAULT NULL,
  `internal_test_marks` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `temp_ugproject_internal_assessment_marks`
--

CREATE TABLE `temp_ugproject_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `component1_marks` varchar(5) DEFAULT NULL,
  `component2_marks` varchar(5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `timetable_csv_dump`
--

CREATE TABLE `timetable_csv_dump` (
  `class_id` int(11) NOT NULL,
  `weekday` varchar(20) NOT NULL,
  `Hour` int(11) NOT NULL,
  `subject_code` varchar(100) NOT NULL,
  `subject_id` int(11) DEFAULT NULL,
  `building_name` varchar(100) NOT NULL,
  `class_hall_name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `uglab_internal_assessment_marks`
--

CREATE TABLE `uglab_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `day_to_day_marks` decimal(4,2) NOT NULL,
  `internal_test_marks` decimal(4,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ugproject_internal_assessment_marks`
--

CREATE TABLE `ugproject_internal_assessment_marks` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `assessment_number` int(11) NOT NULL,
  `component1_marks` decimal(4,2) NOT NULL,
  `component2_marks` decimal(4,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(5) NOT NULL,
  `username` varchar(20) NOT NULL,
  `password` varchar(255) NOT NULL,
  `name` varchar(100) NOT NULL,
  `mobile` varchar(20) DEFAULT NULL,
  `email` varchar(60) DEFAULT NULL,
  `role` varchar(20) NOT NULL,
  `status` int(5) NOT NULL,
  `updatedat` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `academic_years`
--
ALTER TABLE `academic_years`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `acad_year` (`acad_year`);

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `assessment_components`
--
ALTER TABLE `assessment_components`
  ADD PRIMARY KEY (`id`),
  ADD KEY `assessment_id` (`assessment_id`);

--
-- Indexes for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `component_id` (`component_id`,`question_label`),
  ADD KEY `blooms_level_id` (`blooms_level_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stu_id` (`stu_id`,`sub_id`,`date`,`hour`),
  ADD KEY `stu` (`stu_id`),
  ADD KEY `sub` (`sub_id`);

--
-- Indexes for table `attendance_delete_requests`
--
ALTER TABLE `attendance_delete_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculty_id` (`faculty_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `attendance_rules`
--
ALTER TABLE `attendance_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reg_atte_fk1` (`reg_id`);

--
-- Indexes for table `blooms_levels`
--
ALTER TABLE `blooms_levels`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `buildings`
--
ALTER TABLE `buildings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `building_name` (`building_name`);

--
-- Indexes for table `cia_attachments`
--
ALTER TABLE `cia_attachments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `yearsem` (`acad_year`,`yearsem`,`spec_id`) USING BTREE,
  ADD KEY `spec` (`spec_id`),
  ADD KEY `classes_reg_fk1` (`reg_id`);

--
-- Indexes for table `class_timings`
--
ALTER TABLE `class_timings`
  ADD UNIQUE KEY `id` (`id`),
  ADD KEY `timing_id` (`timing_id`);

--
-- Indexes for table `class_timing_schedule`
--
ALTER TABLE `class_timing_schedule`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_class_date` (`class_id`,`from_date`,`to_date`),
  ADD KEY `idx_timing_id` (`timing_id`);

--
-- Indexes for table `course_outcomes`
--
ALTER TABLE `course_outcomes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `co_po_mapping`
--
ALTER TABLE `co_po_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `co_id` (`co_id`),
  ADD KEY `po_id` (`po_id`);

--
-- Indexes for table `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `diary`
--
ALTER TABLE `diary`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sub_id` (`sub_id`,`faculty_id`,`date`,`hour`),
  ADD KEY `diary` (`faculty_id`),
  ADD KEY `update` (`sub_id`);

--
-- Indexes for table `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `faculties_ibfk_2` (`username`),
  ADD KEY `faculties_ibfk_1` (`facultyid`),
  ADD KEY `faculties_ibfk_3` (`dept_id`);

--
-- Indexes for table `faculty_sub`
--
ALTER TABLE `faculty_sub`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `faculty_id` (`faculty_id`,`sub_id`),
  ADD KEY `fac` (`faculty_id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `fac_activity_logs`
--
ALTER TABLE `fac_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `halls`
--
ALTER TABLE `halls`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_building_hall` (`building_id`,`hall_name`);

--
-- Indexes for table `internal_assessments`
--
ALTER TABLE `internal_assessments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `internal_assessment_marks`
--
ALTER TABLE `internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_id_2` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `pg_internal_assessment_marks`
--
ALTER TABLE `pg_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `po_pso`
--
ALTER TABLE `po_pso`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `regulation` (`acad_year`,`regulation`,`specid`,`code`) USING BTREE,
  ADD KEY `specid` (`specid`);

--
-- Indexes for table `programs`
--
ALTER TABLE `programs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `program_code` (`program_code`);

--
-- Indexes for table `question_co_mapping`
--
ALTER TABLE `question_co_mapping`
  ADD PRIMARY KEY (`id`),
  ADD KEY `question_id` (`question_id`),
  ADD KEY `co_id` (`co_id`);

--
-- Indexes for table `regulations`
--
ALTER TABLE `regulations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reg_prg_fk1` (`prog_id`);

--
-- Indexes for table `specialization`
--
ALTER TABLE `specialization`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dept_id` (`dept_id`),
  ADD KEY `prog_id` (`prog_id`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `students_ibfk_2` (`username`);

--
-- Indexes for table `student_ces_feedback`
--
ALTER TABLE `student_ces_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_ces` (`student_id`,`subject_id`),
  ADD KEY `idx_ces_subject` (`subject_id`);

--
-- Indexes for table `student_co_feedback`
--
ALTER TABLE `student_co_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_feedback` (`student_id`,`subject_id`,`co_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `co_id` (`co_id`);

--
-- Indexes for table `student_faculty_feedback`
--
ALTER TABLE `student_faculty_feedback`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_fac_sub` (`student_id`,`subject_id`,`faculty_id`),
  ADD KEY `idx_fac_feedback_faculty` (`faculty_id`),
  ADD KEY `fk_facfb_subject` (`subject_id`);

--
-- Indexes for table `student_marks`
--
ALTER TABLE `student_marks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stu_id` (`stu_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `student_permissions`
--
ALTER TABLE `student_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `stu_id` (`stu_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `date` (`date`),
  ADD KEY `hour` (`hour`);

--
-- Indexes for table `student_questionnaire_responses`
--
ALTER TABLE `student_questionnaire_responses`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_subject_question` (`student_id`,`subject_id`,`question_id`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Indexes for table `student_sub`
--
ALTER TABLE `student_sub`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `stu_id_2` (`stu_id`,`sub_id`),
  ADD KEY `stu_id` (`stu_id`),
  ADD KEY `sub_id` (`sub_id`);

--
-- Indexes for table `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subcode` (`subcode`,`class_id`),
  ADD KEY `class_id` (`class_id`);

--
-- Indexes for table `subject_questionnaire_questions`
--
ALTER TABLE `subject_questionnaire_questions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_id_question_number` (`subject_id`,`question_number`),
  ADD KEY `created_by_user_id` (`created_by_user_id`);

--
-- Indexes for table `temp_internal_assessment_marks`
--
ALTER TABLE `temp_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `temp_joiningdates`
--
ALTER TABLE `temp_joiningdates`
  ADD UNIQUE KEY `htno` (`htno`);

--
-- Indexes for table `temp_pg_internal_assessment_marks`
--
ALTER TABLE `temp_pg_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `temp_uglab_internal_assessment_marks`
--
ALTER TABLE `temp_uglab_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `temp_ugproject_internal_assessment_marks`
--
ALTER TABLE `temp_ugproject_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `timetable_csv_dump`
--
ALTER TABLE `timetable_csv_dump`
  ADD KEY `idx_subject_id` (`subject_id`);

--
-- Indexes for table `uglab_internal_assessment_marks`
--
ALTER TABLE `uglab_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `ugproject_internal_assessment_marks`
--
ALTER TABLE `ugproject_internal_assessment_marks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject_assessment` (`student_id`,`subject_id`,`assessment_number`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`username`),
  ADD UNIQUE KEY `id` (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `academic_years`
--
ALTER TABLE `academic_years`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_components`
--
ALTER TABLE `assessment_components`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_delete_requests`
--
ALTER TABLE `attendance_delete_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance_rules`
--
ALTER TABLE `attendance_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blooms_levels`
--
ALTER TABLE `blooms_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `buildings`
--
ALTER TABLE `buildings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cia_attachments`
--
ALTER TABLE `cia_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_timings`
--
ALTER TABLE `class_timings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `class_timing_schedule`
--
ALTER TABLE `class_timing_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `course_outcomes`
--
ALTER TABLE `course_outcomes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `co_po_mapping`
--
ALTER TABLE `co_po_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `diary`
--
ALTER TABLE `diary`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `faculty_sub`
--
ALTER TABLE `faculty_sub`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `fac_activity_logs`
--
ALTER TABLE `fac_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `halls`
--
ALTER TABLE `halls`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internal_assessments`
--
ALTER TABLE `internal_assessments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `internal_assessment_marks`
--
ALTER TABLE `internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `pg_internal_assessment_marks`
--
ALTER TABLE `pg_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `po_pso`
--
ALTER TABLE `po_pso`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `programs`
--
ALTER TABLE `programs`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `question_co_mapping`
--
ALTER TABLE `question_co_mapping`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `regulations`
--
ALTER TABLE `regulations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `specialization`
--
ALTER TABLE `specialization`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `students`
--
ALTER TABLE `students`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_ces_feedback`
--
ALTER TABLE `student_ces_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_co_feedback`
--
ALTER TABLE `student_co_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_faculty_feedback`
--
ALTER TABLE `student_faculty_feedback`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_marks`
--
ALTER TABLE `student_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_permissions`
--
ALTER TABLE `student_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_questionnaire_responses`
--
ALTER TABLE `student_questionnaire_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `student_sub`
--
ALTER TABLE `student_sub`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subject_questionnaire_questions`
--
ALTER TABLE `subject_questionnaire_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_internal_assessment_marks`
--
ALTER TABLE `temp_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_pg_internal_assessment_marks`
--
ALTER TABLE `temp_pg_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_uglab_internal_assessment_marks`
--
ALTER TABLE `temp_uglab_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `temp_ugproject_internal_assessment_marks`
--
ALTER TABLE `temp_ugproject_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `uglab_internal_assessment_marks`
--
ALTER TABLE `uglab_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ugproject_internal_assessment_marks`
--
ALTER TABLE `ugproject_internal_assessment_marks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(5) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `assessment_components`
--
ALTER TABLE `assessment_components`
  ADD CONSTRAINT `assessment_components_ibfk_1` FOREIGN KEY (`assessment_id`) REFERENCES `internal_assessments` (`id`);

--
-- Constraints for table `assessment_questions`
--
ALTER TABLE `assessment_questions`
  ADD CONSTRAINT `assessment_questions_ibfk_2` FOREIGN KEY (`component_id`) REFERENCES `assessment_components` (`id`),
  ADD CONSTRAINT `assessment_questions_ibfk_3` FOREIGN KEY (`blooms_level_id`) REFERENCES `blooms_levels` (`id`);

--
-- Constraints for table `attendance`
--
ALTER TABLE `attendance`
  ADD CONSTRAINT `attendance_ibfk_1` FOREIGN KEY (`stu_id`) REFERENCES `students` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `attendance_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `attendance_delete_requests`
--
ALTER TABLE `attendance_delete_requests`
  ADD CONSTRAINT `attendance_delete_requests_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`),
  ADD CONSTRAINT `attendance_delete_requests_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `attendance_rules`
--
ALTER TABLE `attendance_rules`
  ADD CONSTRAINT `reg_atte_fk1` FOREIGN KEY (`reg_id`) REFERENCES `regulations` (`id`);

--
-- Constraints for table `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`spec_id`) REFERENCES `specialization` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `classes_reg_fk1` FOREIGN KEY (`reg_id`) REFERENCES `regulations` (`id`);

--
-- Constraints for table `class_timing_schedule`
--
ALTER TABLE `class_timing_schedule`
  ADD CONSTRAINT `fk_cts_class` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cts_timing` FOREIGN KEY (`timing_id`) REFERENCES `class_timings` (`timing_id`);

--
-- Constraints for table `course_outcomes`
--
ALTER TABLE `course_outcomes`
  ADD CONSTRAINT `course_outcomes_ibfk_1` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `co_po_mapping`
--
ALTER TABLE `co_po_mapping`
  ADD CONSTRAINT `co_po_mapping_ibfk_1` FOREIGN KEY (`co_id`) REFERENCES `course_outcomes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `co_po_mapping_ibfk_2` FOREIGN KEY (`po_id`) REFERENCES `po_pso` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `diary`
--
ALTER TABLE `diary`
  ADD CONSTRAINT `diary_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `diary_ibfk_2` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `faculties`
--
ALTER TABLE `faculties`
  ADD CONSTRAINT `faculties_ibfk_1` FOREIGN KEY (`facultyid`) REFERENCES `users` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `faculties_ibfk_2` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON UPDATE CASCADE,
  ADD CONSTRAINT `faculties_ibfk_3` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `faculty_sub`
--
ALTER TABLE `faculty_sub`
  ADD CONSTRAINT `faculty_sub_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `faculty_sub_ibfk_3` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `fac_activity_logs`
--
ALTER TABLE `fac_activity_logs`
  ADD CONSTRAINT `fac_activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `halls`
--
ALTER TABLE `halls`
  ADD CONSTRAINT `halls_ibfk_1` FOREIGN KEY (`building_id`) REFERENCES `buildings` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `internal_assessments`
--
ALTER TABLE `internal_assessments`
  ADD CONSTRAINT `internal_assessments_ibfk_1` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `internal_assessment_marks`
--
ALTER TABLE `internal_assessment_marks`
  ADD CONSTRAINT `internal_assessment_marks_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `internal_assessment_marks_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`);

--
-- Constraints for table `po_pso`
--
ALTER TABLE `po_pso`
  ADD CONSTRAINT `po_pso_ibfk_1` FOREIGN KEY (`specid`) REFERENCES `specialization` (`id`);

--
-- Constraints for table `question_co_mapping`
--
ALTER TABLE `question_co_mapping`
  ADD CONSTRAINT `question_co_mapping_ibfk_1` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`),
  ADD CONSTRAINT `question_co_mapping_ibfk_2` FOREIGN KEY (`co_id`) REFERENCES `course_outcomes` (`id`);

--
-- Constraints for table `regulations`
--
ALTER TABLE `regulations`
  ADD CONSTRAINT `reg_prg_fk1` FOREIGN KEY (`prog_id`) REFERENCES `programs` (`id`);

--
-- Constraints for table `specialization`
--
ALTER TABLE `specialization`
  ADD CONSTRAINT `specialization_ibfk_3` FOREIGN KEY (`dept_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `specialization_ibfk_4` FOREIGN KEY (`prog_id`) REFERENCES `programs` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`username`) REFERENCES `users` (`username`) ON UPDATE CASCADE;

--
-- Constraints for table `student_ces_feedback`
--
ALTER TABLE `student_ces_feedback`
  ADD CONSTRAINT `fk_ces_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ces_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_co_feedback`
--
ALTER TABLE `student_co_feedback`
  ADD CONSTRAINT `student_co_feedback_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`),
  ADD CONSTRAINT `student_co_feedback_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`),
  ADD CONSTRAINT `student_co_feedback_ibfk_3` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`),
  ADD CONSTRAINT `student_co_feedback_ibfk_4` FOREIGN KEY (`co_id`) REFERENCES `course_outcomes` (`id`);

--
-- Constraints for table `student_faculty_feedback`
--
ALTER TABLE `student_faculty_feedback`
  ADD CONSTRAINT `fk_facfb_faculty` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_facfb_student` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_facfb_subject` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_marks`
--
ALTER TABLE `student_marks`
  ADD CONSTRAINT `student_marks_ibfk_1` FOREIGN KEY (`stu_id`) REFERENCES `student_sub` (`stu_id`),
  ADD CONSTRAINT `student_marks_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `assessment_questions` (`id`);

--
-- Constraints for table `student_questionnaire_responses`
--
ALTER TABLE `student_questionnaire_responses`
  ADD CONSTRAINT `student_questionnaire_responses_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_questionnaire_responses_ibfk_2` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_questionnaire_responses_ibfk_3` FOREIGN KEY (`question_id`) REFERENCES `subject_questionnaire_questions` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `student_sub`
--
ALTER TABLE `student_sub`
  ADD CONSTRAINT `student_sub_ibfk_3` FOREIGN KEY (`stu_id`) REFERENCES `students` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `student_sub_ibfk_4` FOREIGN KEY (`sub_id`) REFERENCES `subjects` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `subject_questionnaire_questions`
--
ALTER TABLE `subject_questionnaire_questions`
  ADD CONSTRAINT `subject_questionnaire_questions_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `subject_questionnaire_questions_ibfk_2` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
