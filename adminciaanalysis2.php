<?php
session_start();
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

$selected_sub_id = null;
$selected_assessment_number = null;

require_once("admin.class.php");
$adminObj = new Admin();

$departments = $adminObj->getAllDepartments();

if (!empty($_POST['dept_id'])) {
    $selected_dept_id = $_POST['dept_id'];
    if (!empty($_POST['cls_id'])) {
        $selected_cls_id = $_POST['cls_id'];
    }
    require_once("hod.class.php");
    $hodObj = new HOD();

    $selected_cls_id = null;
    $selected_sub_id = null;

    if (!empty($_POST['cls_id'])) {
        $selected_cls_id = $_POST['cls_id'];
        $facultySubjects = $hodObj->getSubjectsByClassID($selected_cls_id);
    }


    $specializations = $hodObj->getSpecializationsByDepartment($selected_dept_id);

    $classes_options = '';

    if (!empty($specializations)) {
        foreach ($specializations as $spec) {
            $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
            $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
            if (!empty($classes)) {
                foreach ($classes as $class) {
                    $classes_options .= '<option value="' . $class['id'] . '"';
                    if ($class['id'] == $selected_cls_id) {
                        $classes_options .= ' selected';
                    }
                    $classes_options .= '>' . $class['classname'] . '</option>';
                }
            }
            $classes_options .= '</optgroup>';
        }
    }
    // Handle subject selection
    if (!empty($_POST['sub_id'])) {
        if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
            unset($_SESSION['secretcode']);
        }
        $selected_sub_id = $_POST['sub_id'];

        if (!empty($_POST['assessment_number'])) {
            $selected_assessment_number = $_POST['assessment_number'];
        }
    }
}
// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("adminheader.php");

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Course CIA Analysis</div>
                <div class="card-body">
                    <form action="adminciaanalysis2.php" method="post" id="ciacourseform">
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
                            <label for="cls_id">Class:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php echo $classes_options; ?>
                            </select>
                        </div>
                        <input type="hidden" name="an" value="ab" />
                        <br />
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                if (!empty($facultySubjects['data'])) {
                                    foreach ($facultySubjects['data'] as $subject) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    </form>
                </div>
            </div>

            <?php if (!empty($_SESSION["succ"])) {
                echo '<div class="alert alert-success">' . $_SESSION["succ"] . '</div>';
                unset($_SESSION["succ"]);
            } ?>
            <?php if (!empty($_SESSION["err"])) {
                echo '<div class="alert alert-danger">' . $_SESSION["err"] . '</div>';
                unset($_SESSION["err"]);
            } ?>
        </div>
    </div>

    <?php if (!empty($selected_sub_id)) : ?>
        <?php
        $selected_sub_type = 'theory';
        if (!empty($facultySubjects['data'])) {
            foreach ($facultySubjects['data'] as $subject) {
                if (!empty($subject['id']) && $subject['id'] == $selected_sub_id) {
                    $selected_sub_type = strtolower($subject['sub_type'] ?? 'theory');
                    break;
                }
            }
        }
        if ($selected_sub_type === 'lab' || $selected_sub_type === 'dti') {
            if (empty($selected_assessment_number) || $selected_assessment_number == '2') {
                $selected_assessment_number = '1';
            }
        }
        ?>
        <br>
        <div class="card">
            <div class="card-header">
                Select Assessment
            </div>
            <div class="card-body">
                <form action="adminciaanalysis2.php" method="post" id="assessmentForm">
                    <select name="assessment_number" id="assessment_number" class="form-select" required onchange="document.getElementById('assessmentForm').submit();">
                        <option value="">--Select Assessment --</option>
                        <?php
                        if ($selected_sub_type === 'lab' || $selected_sub_type === 'dti') {
                            $assessments = [1 => "Lab CIA"];
                        } else {
                            $assessments = [1 => "CIA 1", 2 => "CIA 2", 'all' => "Overall CIA"];
                        }
                        foreach ($assessments as $num => $label):
                            $selected = (!empty($selected_assessment_number) && $selected_assessment_number == $num) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($num); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($label); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" name="dept_id" value="<?php echo $selected_dept_id; ?>" />
                    <input type="hidden" name="cls_id" value="<?php echo $selected_cls_id; ?>" />
                    <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>" />
                </form>
            </div>
        </div>
        <?php if ($selected_sub_id && $selected_assessment_number): ?>
            <?php
            $subject_details_header = "";
            // Get Subject Details for Header
            if ($selected_sub_id && !empty($facultySubjects)) {
                foreach ($facultySubjects['data'] as $subject) {
                    if (!empty($subject['id']) && $subject['id'] == $selected_sub_id) {
                        $subject_details_header = "{$subject['sub_fullname']} ({$subject['subcode']})";
                        break;
                    }
                }
            }
            ?>

            <?php
            require_once("ciaanalysis2_common_chart.php");
            echo renderAnalysisAssets(); // Include necessary JS libraries and global scripts
            echo renderAnalysisScripts($selected_sub_id, $selected_assessment_number); // Include the analysis scripts
            ?>



        <?php endif; // End check for selected_sub_id && selected_assessment_number 
        ?>
        <br>
    <?php endif; ?>
</div>
<script>
    document.getElementById('dept_id').addEventListener('change', function() {
        document.getElementById("ciacourseform").submit();
    });

    document.getElementById('cls_id').addEventListener('change', function() {
        document.getElementById("ciacourseform").submit();
    });
    document.getElementById('sub_id').addEventListener('change', function() {
        document.getElementById("ciacourseform").submit();
    });
</script>
<?php
require_once("adminfooter.php");
?>