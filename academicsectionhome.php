<?php
session_start();
$page_title = "Home";
require_once("academicsectionheader.php");
?>

<div class="container">
    <br />

    <div class="card shadow-sm mb-4">
        <div class="card-header">
            Academic Section Dashboard
        </div>
        <div class="card-body">
            <h5 class="mb-3">Welcome to the Academic Section Portal</h5>
            <p class="mb-0">
                This portal supports institution-level academic operations for the Academic Section role.
                It currently provides access to academic setup modules, attendance monitoring, syllabus management,
                regulation management, and account support actions.
            </p>
        </div>
    </div>

    <div class="row g-3">

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header">Academic Setup</div>
                <div class="card-body">
                    <p class="mb-3">
                        Maintain the shared academic master data used across institutional workflows.
                    </p>
                    <ul class="mb-3">
                        <li>Manage buildings and halls used in timetable and room allocation workflows.</li>
                        <li>View and manage regulations required for academic structure mapping.</li>
                        <li>Upload and maintain syllabus records in a structured and grouped format.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <a href="academicsectionmanagebuildings.php" class="btn btn-outline-primary">
                            <i class="bi bi-building me-1"></i> Buildings & Halls
                        </a>
                        <a href="academicsectionregulations.php" class="btn btn-outline-primary">
                            <i class="bi bi-diagram-3 me-1"></i> Regulations
                        </a>
                        <a href="academicsectionsyllabus.php" class="btn btn-outline-primary">
                            <i class="bi bi-file-earmark-text me-1"></i> Syllabus
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header">Academic Views</div>
                <div class="card-body">
                    <p class="mb-3">
                        Access institution-level academic visibility tools for monitoring and reference.
                    </p>
                    <ul class="mb-3">
                        <li>View class attendance reports across departments and classes.</li>
                        <li>View faculty-wise timetable across departments for academic monitoring and reference.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <a href="academicsectionshowallclsattendance.php" class="btn btn-outline-primary">
                            <i class="bi bi-calendar-check me-1"></i> View Attendance
                        </a>
                        <a href="faculty_weekly_timetable.php" class="btn btn-outline-primary">
                            <i class="bi bi-table me-1"></i> View Timetables
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-header">Account Support</div>
                <div class="card-body">
                    <p class="mb-3">
                        Perform user support and account maintenance functions assigned to Academic Section.
                    </p>
                    <ul class="mb-3">
                        <li>Reset student passwords when required.</li>
                        <li>Reset faculty passwords for operational support.</li>
                        <li>Update your own login password securely.</li>
                    </ul>
                    <div class="d-grid gap-2">
                        <a href="academicsectionresetstudentpwd.php" class="btn btn-outline-primary">
                            <i class="bi bi-person me-1"></i> Reset Student Pwd
                        </a>
                        <a href="academicsectionresetfacultypwd.php" class="btn btn-outline-primary">
                            <i class="bi bi-briefcase me-1"></i> Reset Faculty Pwd
                        </a>
                        <a href="academicsectionchgpwd.php" class="btn btn-outline-primary">
                            <i class="bi bi-shield-lock me-1"></i> Change Password
                        </a>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="card shadow-sm mt-4">
        <div class="card-header">Role Notes</div>
        <div class="card-body">
            <ul class="mb-0">
                <li>Academic Section is evolving into an institution-level academic operations role.</li>
                <li>Some modules are currently management-enabled, while some future modules will initially be provided as read-only views.</li>
                <li>Class and timetable view permissions can be added next without disrupting the current menu structure.</li>
                <li>All updates performed through this role should remain aligned with existing administrative and academic workflows.</li>

            </ul>
        </div>
    </div>

</div>

<?php
require_once("academicsectionfooter.php");
?>
