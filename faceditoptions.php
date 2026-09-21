<?php
session_start();
$page_title = "Edit"; // Changed title slightly for clarity
require_once("facheader.php"); // Ensure this includes Bootstrap CSS and potentially Bootstrap Icons CSS
?>

<div class="container mt-4">
    <!--All these in a card layout-->
    <div class="card">
        <div class="card-body">
            <div class="row row-cols-1 row-cols-md-2 g-5">

                <div class="col">
                    <div class="card h-100 shadow-sm border-primary">
                        <div class="card-header bg-primary-subtle">
                            <i class="bi bi-diagram-3 me-2"></i>Curriculum Outcomes
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text">Manage Course Outcomes (COs) and their mapping to Program Outcomes (POs/PSOs).</p>
                            <div class="mt-auto">
                                <a href="facaddcos.php" class="btn btn-outline-primary d-block mb-2">
                                    <i class="bi bi-file-earmark-plus me-1"></i> Add / View Course Outcomes (COs)
                                </a>
                                <a href="facarticulationmatrix.php" class="btn btn-outline-primary d-block">
                                    <i class="bi bi-table me-1"></i> Add / View CO-PO/PSO Articulation Matrix
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card h-100 shadow-sm border-success">
                        <div class="card-header bg-success-subtle">
                            <i class="bi bi-clipboard2-data me-2"></i>Assessments & Analysis
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text">Enter assessment marks, manage related files, and view performance analysis.</p>
                            <div class="mt-auto">
                                <a href="facciamarks.php" class="btn btn-outline-success d-block mb-2">
                                    <i class="bi bi-pencil-square me-1"></i> Enter CIA Marks & MetaData
                                </a>
                                <a href="facciaanalysis2.php" class="btn btn-outline-success d-block">
                                    <i class="bi bi-graph-up me-1"></i> View CIA Analysis
                                </a>
                                <a href="facviewfeedback.php" class="btn btn-outline-success d-block mt-2">
                                    <i class="bi bi-chat-dots me-1"></i> View Student Feedbacks
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card h-100 shadow-sm border-info">
                        <div class="card-header bg-info-subtle">
                            <i class="bi bi-journal-text me-2"></i>Class Diary & Attendance
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text">Add Diary Only without Attendance, or mark attendance for students who joined late (For ex: Lateral Entry Students).</p>
                            <div class="mt-auto">
                                <a href="facadddairy.php" class="btn btn-outline-info d-block mb-2">
                                    <i class="bi bi-calendar-plus me-1"></i> Add Diary Only (No Attendance)
                                </a>
                                <a href="facexceptionalattendance.php" class="btn btn-outline-info d-block mb-2">
                                    <i class="bi bi-exclamation-circle me-1"></i> Mark Attendance (For exceptional Cases)
                                </a>
                                <a href="facupdatestudentattendance.php" class="btn btn-outline-info d-block">
                                    <i class="bi bi-arrow-repeat me-1"></i> Update Single Student Attendance
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card h-100 shadow-sm border-warning">
                        <div class="card-header bg-warning-subtle text-dark">
                            <i class="bi bi-envelope-exclamation me-2"></i>Attendance Deletion Requests
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text">Request deletion of attendance records from the Head of Department (HoD).</p>
                            <div class="mt-auto">
                                <a href="facadddelattrequest.php" class="btn btn-outline-warning d-block mb-2">
                                    <i class="bi bi-send me-1"></i> Submit New Deletion Request
                                </a>
                                <a href="facshowdelattrequests.php" class="btn btn-outline-secondary d-block">
                                    <i class="bi bi-list-check me-1"></i> View Submitted Requests
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col">
                    <div class="card h-100 shadow-sm border-success">
                        <div class="card-header bg-success-subtle text-dark">
                            <i class="bi bi-bookmarks me-2"></i>Electives & Shared Subjects Utilities
                        </div>
                        <div class="card-body d-flex flex-column">
                            <p class="card-text">Tools for subjects taught across multiple sections (e.g., Open Electives). Mark combined attendance or import CIA data.</p>
                            <div class="mt-auto">
                                <a href="facaddgroupedattendance.php" class="btn btn-outline-info d-block mb-2">
                                    <i class="bi bi-pencil-square me-1"></i> Mark Attendance (For Grouped Sessions)
                                </a>

                                <a href="facgroupedcia.php" class="btn btn-outline-info d-block">
                                    <i class="bi bi-arrows-collapse me-1"></i> Import Grouped CIA Data
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div> <?php
        require_once("facfooter.php");
        ?>