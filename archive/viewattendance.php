<?php
session_start();
$page_title = "View Attendance";
require_once("stheader.php");

require_once("student.class.php");
$student = new Student();
$attendance = $student->getAttendance($_SESSION['user_id']);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-header bg-success text-white text-center">
                    <h4>Attendance Details</h4>
                </div>
                <div class="card-body">
                    <?php if ($attendance['status'] == 1): ?>
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Subject</th>
                                    <th>Attendance Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendance['data'] as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['subject_name']); ?></td>
                                        <td><?= htmlspecialchars($record['attendance_percentage']); ?>%</td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">No attendance records available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("studentfooter.php");
?>