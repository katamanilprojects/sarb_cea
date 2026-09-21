<?php
session_start();
$page_title = "Manage Faculty";
require_once("hod.class.php");
require_once("hodheader.php");

$hodObj = new HOD();
$faculties = $hodObj->getFacultyByDepartment($_SESSION["dept_id"]);
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Faculty Details
        </div>
        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th scope="col">Username</th>
                        <th scope="col">Name</th>
                        <th scope="col">Designation</th>
                        <th scope="col">Mobile</th>
                        <th scope="col">Status</th>
                        <th scope="col">Profile Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faculties['data'] as $faculty): ?>
                        <tr class="<?= $faculty['status'] == 1 ? '' : 'text-muted fst-italic' ?>">
                            <td><?= htmlspecialchars($faculty['username']) ?></td>
                            <td><?= htmlspecialchars($faculty['name']) ?></td>
                            <td><?= htmlspecialchars($faculty['designation']) ?></td>
                            <td><?= htmlspecialchars($faculty['mobile']) ?></td>
                            <td class="text-capitalize <?= $faculty['status'] == 1 ? 'text-success' : 'text-danger' ?>">
                                <?= $faculty['status'] == 1 ? 'Active' : 'Inactive' ?>
                            </td>
                            <td>
                                <form action="hodfacultyprofile.php" method="post">
                                    <input type="hidden" name="faculty_id" value="<?= $faculty['id'] ?>">
                                    <button type="submit" class="btn btn-sm <?= $faculty['status'] == 1 ? 'btn-outline-primary' : 'btn-outline-secondary' ?>">
                                        <i class="bi bi-person-gear"></i> Manage Profile
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            
        </div>
    </div>
    <?php if (isset($succ)): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($succ) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

</div>

<?php
require_once("hodfooter.php");
?>