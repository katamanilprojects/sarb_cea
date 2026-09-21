<?php
session_start();
$page_title = "Departments";
require_once("adminheader.php");
require_once("admin.class.php");

// Redirect if dept_id is not present
if (!isset($_POST['dept_id'])) {
    header("Location: adminfacst.php");
    exit();
}

$dept_id = $_POST['dept_id'];
$adminObj = new Admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    $result = $adminObj->addFaculty($_POST);

    if ($result['status'] === 1) {
        $succ = "Faculty Added Successfully";
    } else {
        $error = "Error adding faculty. Please try again.";
    }
}

$faculties = $adminObj->getFacultyByDepartment($dept_id);

?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                <?php
                if (!empty($_POST["dept_fullname"])) {
                    echo '<h5>Department of ' . htmlspecialchars($_POST["dept_fullname"]) . "</h5>";
                }
                ?>
            </div>
            <div class="float-end">
                <a href="adminfacst.php" class="btn btn-sm btn-outline-success">All Departments</a>
            </div>
        </div>
        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Name</th>
                        <th>Designation</th>
                        <th>Mobile</th>
                        <th>Status</th>
                        <th>Profile Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($faculties['data'] as $faculty): ?>
                        <tr style="color: <?= $faculty['status'] == 1 ? 'black' : '#ff6969' ?>">
                            <td><?= htmlspecialchars($faculty['username']) ?></td>
                            <td><?= htmlspecialchars($faculty['name']) ?></td>
                            <td><?= htmlspecialchars($faculty['designation']) ?></td>
                            <td><?= htmlspecialchars($faculty['mobile']) ?></td>
                            <td><?= $faculty['status'] == 1 ? 'Active' : 'Inactive' ?></td>
                            <td>
                                <form action="adminfacultyprofile.php" method="post">
                                    <input type="hidden" name="faculty_id" value="<?= $faculty['id'] ?>">
                                    <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST["dept_id"]) ?>">
                                    <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">
                                    <button type="submit" class="btn btn-info">Profile Details</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <form action="adminaddfaculty.php" method="post">
                <input type="hidden" name="dept_id" value="<?= htmlspecialchars($_POST["dept_id"]) ?>">
                <input type="hidden" name="dept_fullname" value="<?= htmlspecialchars($_POST['dept_fullname']) ?>">
                <button type="submit" class="btn btn-success">Add New Faculty</button>
            </form>
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
require_once("adminfooter.php");
?>