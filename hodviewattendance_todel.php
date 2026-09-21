<?php
session_start();
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");

$hod = new HOD($_SESSION['user_id']);
$attendance = $hod->getAttendanceByDept($_SESSION['dept_id']);

?>

<div class="container">
    <h2>Department Attendance</h2>
    <?php if ($attendance['status'] == 1): ?>
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Attendance Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($attendance['data'] as $student): ?>
                    <tr>
                        <td><?= htmlspecialchars($student['name']); ?></td>
                        <td><?= htmlspecialchars($student['attendance_percentage']); ?>%</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="alert alert-danger">No attendance data available.</div>
    <?php endif; ?>
</div>

<?php
require_once("hodfooter.php");
?>