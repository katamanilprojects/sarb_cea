<?php
session_start();
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Home";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

$depts = $superadmin->getAllDepartments();
$programs = $superadmin->getAllPrograms();
$specs = $superadmin->getAllSpecializations();
$classes = $superadmin->getAllClasses();
$admins = $superadmin->getAllAdmins();

?>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-primary mb-3">
                <div class="card-header">Departments</div>
                <div class="card-body">
                    <h5 class="card-title">Total: <?= count($depts['data']) ?></h5>
                    <a href="superadmindepts.php" class="btn btn-light">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-success mb-3">
                <div class="card-header">Programs</div>
                <div class="card-body">
                    <h5 class="card-title">Total: <?= count($programs['data']) ?></h5>
                    <a href="superadminprograms.php" class="btn btn-light">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-info mb-3">
                <div class="card-header">Specializations</div>
                <div class="card-body">
                    <h5 class="card-title">Total: <?= count($specs['data']) ?></h5>
                    <a href="superadminspecs.php" class="btn btn-light">Manage</a>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-4">
            <div class="card text-white bg-warning mb-3">
                <div class="card-header">Classes</div>
                <div class="card-body">
                    <h5 class="card-title">Total: <?= count($classes['data']) ?></h5>
                    <a href="superadminclasses.php" class="btn btn-light">Manage</a>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card text-white bg-danger mb-3">
                <div class="card-header">Admins</div>
                <div class="card-body">
                    <h5 class="card-title">Total: <?= count($admins['data']) ?></h5>
                    <a href="superadminadmins.php" class="btn btn-light">Manage</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once("superadminfooter.php");
?>