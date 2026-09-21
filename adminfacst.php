<?php
session_start();

$page_title = "Departments";
require_once("adminheader.php");

require_once("admin.class.php");
$obj = new Admin();


$departments = $obj->getAllDepartments();

$_SESSION['secretcode'] = bin2hex(random_bytes(32));
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Departments
        </div>
        <div class="card-body">

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Department</th>
                        <th>Department Full Name</th>
                        <th colspan="3">Users</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($departments['data'] as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['dept_shortname']); ?></td>
                            <td><?= htmlspecialchars($dept['dept_fullname']); ?></td>
                            <td>
                                <?php if($dept['dept_shortname']!="BTech1"){ ?>
                                <form action="adminfac.php" method="post">
                                    <input type="hidden" name="dept_id" value="<?= $dept['id']; ?>" />
                                    <input type="hidden" name="dept_fullname" value="<?= $dept['dept_fullname']; ?>" />
                                    <button type="submit" class="btn btn-outline-success">Faculty</button>
                                </form>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if($dept['dept_shortname']!="humanities" && $dept['dept_shortname']!="physics" && $dept['dept_shortname']!="chemistry" && $dept['dept_shortname']!="maths" && $dept['dept_shortname']!="PE"){ ?>
                                <form action="adminviewclasses.php" method="post">
                                    <input type="hidden" name="dept_id" value="<?= $dept['id']; ?>" />
                                    <input type="hidden" name="dept_fullname" value="<?= $dept['dept_fullname']; ?>" />
                                    <button type="submit" class="btn btn-outline-primary">Classes</button>
                                </form>
                                <?php } ?>
                            </td>
                            <td>
                                <form action="hod_profile.php" method="post">
                                    <input type="hidden" name="dept_id" value="<?= $dept['id']; ?>" />
                                    <input type="hidden" name="dept_fullname" value="<?= $dept['dept_fullname']; ?>" />
                                    <button type="submit" class="btn btn-outline-secondary">HOD Details</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php
require_once("adminfooter.php");
?>