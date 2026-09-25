<?php
session_start();

$page_title = "Manage Subjects - Building & Hall";
require_once("header.php");
require_once("hod.class.php");
require_once("admin.class.php");

$hodObj = new HOD();
$adminObj = new Admin();

$departments = $adminObj->getAllDepartments();
$activeYear = $hodObj->getActiveAcademicYear();

// Fetch buildings and halls from database
$buildingsWithHalls = $hodObj->getBuildingsWithHalls();
$buildingHallsJson = json_encode($buildingsWithHalls['data'] ?? []);

$selected_dept_id = null;
$selected_cls_id = null;
$specializations = [];
$classes_options = '';
$subjectsList = [];
$msg = '';
$msg_type = '';

// Handle update
if (!empty($_POST['whattodo']) && $_POST['whattodo'] == 'updatesubject') {
    $subject_id = intval($_POST['subject_id']);
    $class_id = intval($_POST['cls_id']);
    $building_name = trim($_POST['building_name']);
    $hall_name = trim($_POST['hall_name']);
    
    $result = $hodObj->updateTimetableBuildingHall($subject_id, $class_id, $building_name, $hall_name);
    if ($result['status'] === 1) {
        $msg = "Building and Hall updated successfully!";
        $msg_type = 'success';
    } else {
        $msg = $result['err'] ?? "Failed to update";
        $msg_type = 'danger';
    }
}

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];
    if (!empty($_POST['cls_id'])) {
        $selected_cls_id = $_POST['cls_id'];
    }

    $specializations = $hodObj->getSpecializationsByDepartment($selected_dept_id);

    if (!empty($specializations)) {
        foreach ($specializations as $spec) {
            $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
            $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
            if (!empty($classes)) {
                foreach ($classes as $class) {
                    if ($class['acad_year'] == $activeYear) {
                        $classes_options .= '<option value="' . $class['id'] . '"';
                        if ($class['id'] == $selected_cls_id) {
                            $classes_options .= ' selected';
                        }
                        $classes_options .= '>' . $class['classname'] . '</option>';
                    }
                }
            }
            $classes_options .= '</optgroup>';
        }

        if (!empty($selected_cls_id)) {
            $subjectsList = $hodObj->getSubjectsWithBuildingHall($selected_cls_id);
        }
    }
}

?>

<style>
.subject-row:hover { background-color: #f8f9fa; }
.edit-form { display: none; background: #f0f8ff; padding: 10px; border-radius: 5px; }
</style>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <?php if (!empty($msg)): ?>
                <div class="alert alert-<?= $msg_type ?> alert-dismissible fade show">
                    <?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">Manage Subjects - Building & Hall<?php if (!empty($activeYear)) echo " (Academic Year $activeYear)"; ?></div>
                <div class="card-body">
                    <form action="repmanage_subjects.php" method="post" id="viewsubjectsform">
                        <div class="form-group">
                            <label for="dept_id">Department:</label>
                            <select name="dept_id" id="dept_id" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php foreach ($departments['data'] as $dept) {
                                    if ($dept['dept_fullname'] != "Physical Education" && $dept['dept_fullname'] != "Humanities" && $dept['dept_fullname'] != "Physics" && $dept['dept_fullname'] != "Chemistry" && $dept['dept_fullname'] != "Mathematics") {
                                        $selected = (!empty($selected_dept_id) && $selected_dept_id == $dept['id']) ? 'selected' : '';
                                        echo "<option value='{$dept['id']}' $selected>{$dept['dept_fullname']}</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <div class="form-group">
                            <label for="cls_id">Class<?php if (!empty($activeYear)) echo " ($activeYear)"; ?>:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php echo $classes_options; ?>
                            </select>
                        </div>
                        <br />
                        <button type="submit" class="btn btn-primary">View Subjects</button>
                    </form>
                </div>
            </div>
            <br />

            <?php if (!empty($subjectsList['data'])) { ?>
                <div class="card">
                    <div class="card-header">Subjects List - Update Building & Hall</div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>S.No</th>
                                        <th>Subject Code</th>
                                        <th>Subject Name</th>
                                        <th>Type</th>
                                        <th>Building Name</th>
                                        <th>Hall Name</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    foreach ($subjectsList['data'] as $subject) {
                                        echo "<tr class='subject-row' id='row-{$subject['id']}'>";
                                        echo "<td>" . htmlspecialchars($subject['subject_sno']) . "</td>";
                                        echo "<td>" . htmlspecialchars($subject['subcode']) . "</td>";
                                        echo "<td>" . htmlspecialchars($subject['sub_fullname']) . "</td>";
                                        echo "<td>" . htmlspecialchars($subject['sub_type']) . "</td>";
                                        echo "<td id='building-{$subject['id']}'>" . htmlspecialchars($subject['building_name'] ?? '-') . "</td>";
                                        echo "<td id='hall-{$subject['id']}'>" . htmlspecialchars($subject['hall_name'] ?? '-') . "</td>";
                                        echo "<td><button class='btn btn-sm btn-primary' onclick='showEditForm({$subject['id']})'>Edit</button></td>";
                                        echo "</tr>";
                                        
                                        echo "<tr class='edit-form' id='edit-{$subject['id']}'>";
                                        echo "<td colspan='7'>";
                                        echo "<form method='post' action='repmanage_subjects.php'>";
                                        echo "<input type='hidden' name='whattodo' value='updatesubject'>";
                                        echo "<input type='hidden' name='subject_id' value='{$subject['id']}'>";
                                        echo "<input type='hidden' name='dept_id' value='{$selected_dept_id}'>";
                                        echo "<input type='hidden' name='cls_id' value='{$selected_cls_id}'>";
                                        
                                        echo "<div class='row g-3'>";
                                        echo "<div class='col-md-5'>";
                                        echo "<label class='form-label'><strong>Building Name</strong></label>";
                                        echo "<select name='building_name' class='form-select building-select' required>";
                                        echo "<option value=''>-- Select Building --</option>";
                                        if (!empty($buildingsWithHalls['data'])) {
                                            foreach ($buildingsWithHalls['data'] as $building_name => $halls) {
                                                $sel = ($subject['building_name'] == $building_name) ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($building_name) . "' {$sel}>" . htmlspecialchars($building_name) . "</option>";
                                            }
                                        }
                                        echo "</select>";
                                        echo "</div>";
                                        
                                        echo "<div class='col-md-5'>";
                                        echo "<label class='form-label'><strong>Hall Name</strong></label>";
                                        echo "<select name='hall_name' class='form-select hall-select' data-current-hall='" . htmlspecialchars($subject['hall_name'] ?? '') . "' required>";
                                        echo "<option value=''>-- Select Hall --</option>";
                                        echo "</select>";
                                        echo "</div>";
                                        
                                        echo "<div class='col-md-2 d-flex align-items-end'>";
                                        echo "<button type='submit' class='btn btn-success me-2'>Save</button>";
                                        echo "<button type='button' class='btn btn-secondary' onclick='hideEditForm({$subject['id']})'>Cancel</button>";
                                        echo "</div>";
                                        echo "</div>";
                                        
                                        echo "</form>";
                                        echo "</td>";
                                        echo "</tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php } elseif (!empty($selected_cls_id)) { ?>
                <div class="alert alert-info">No subjects found for the selected class.</div>
            <?php } ?>
        </div>
    </div>
</div>

<script>
const buildingRooms = <?= $buildingHallsJson ?>;

function showEditForm(id) {
    document.getElementById('edit-' + id).style.display = 'table-row';
    const buildingSelect = document.querySelector('#edit-' + id + ' .building-select');
    const hallSelect = document.querySelector('#edit-' + id + ' .hall-select');
    const currentHall = hallSelect.getAttribute('data-current-hall');
    
    if (buildingSelect.value) {
        populateHalls(buildingSelect, hallSelect);
        if (currentHall) {
            setTimeout(() => { hallSelect.value = currentHall; }, 50);
        }
    }
    
    buildingSelect.addEventListener('change', function() {
        populateHalls(this, hallSelect);
    });
}

function hideEditForm(id) {
    document.getElementById('edit-' + id).style.display = 'none';
}

function populateHalls(buildingSelect, hallSelect) {
    const building = buildingSelect.value;
    hallSelect.innerHTML = '<option value="">-- Select Hall --</option>';
    
    if (building && buildingRooms[building]) {
        buildingRooms[building].forEach(function(hall) {
            const option = document.createElement('option');
            option.value = hall;
            option.textContent = hall;
            hallSelect.appendChild(option);
        });
    }
}

document.getElementById('dept_id').addEventListener('change', function() {
    document.getElementById('viewsubjectsform').submit();
});
</script>

<?php require_once("hodfooter.php"); ?>
