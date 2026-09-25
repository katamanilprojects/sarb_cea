<?php
session_start();
$page_title = "View Marks";
require_once("stheader.php");

require_once("student.class.php");
$student = new Student();
$marks = $student->getMarks($_SESSION['user_id']);
?>

<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card shadow-lg">
                <div class="card-header bg-info text-white text-center">
                    <h4>Internal Assessment Marks</h4>
                </div>
                <div class="card-body">
                    <?php if ($marks['status'] == 1): ?>
                        <table class="table table-bordered table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Subject</th>
                                    <th>Marks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($marks['data'] as $record): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($record['subject_name']); ?></td>
                                        <td><?= htmlspecialchars($record['marks']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="alert alert-warning text-center">No marks records available.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("studentfooter.php");
?>