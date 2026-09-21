<?php
session_start();
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");

// Redirect if class_id is not provided
if (empty($_POST['class_id'])) {
    header("Location: hodviewclasses.php");
    exit();
}

$obj = new HOD();
$class_id = $_POST['class_id'];

// Handle form submission
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "editsubcode" && !empty($_POST['subject_id']) && !empty($_POST['new_subcode'])) {
        $res_arr = $obj->updateSubjectCode($_POST['subject_id'], $_POST['new_subcode'], $class_id);
        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Subject code updated successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to update subject code. Please try again.";
        }
    }

    if (!empty($_POST['subject_sno']) && !empty($_POST['subcode']) && !empty($_POST['sub_shortname']) && !empty($_POST['sub_fullname']) && !empty($_POST['sub_type']) && !empty($_POST['class_id'])) {

        $res_arr = $obj->addSubject($_POST);

        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Subject added successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to add subject. Please try again.";
        }
    }

    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "deletesubject" && !empty($_POST['subject_id'])) {

        $res_arr = $obj->deleteSubjectByID($_POST['subject_id']);

        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Subject Deleted successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to delete subject. Please try again.";
        }
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Fetch students for the selected class
$subjectList = $obj->getSubjectsByClassID($class_id);
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                Specialization: <?= htmlspecialchars($_POST['spec_fullname']) ?>
            </div>
            <div class="float-end">
                <a href="hodviewclasses.php" class="btn btn-sm btn-outline-success">All Classes</a>
            </div>
        </div>
        <div class="card-header">
            Class: <?= htmlspecialchars($_POST['class_fullname']) ?>
        </div>
    </div>
</div>
<div class="container">
    <div class="row">
        <div class="col-sm-6">
            <div class="card">
                <div class="card-header">Add New Subject</div>
                <div class="card-body">
                    <form action="hodviewsubjects.php" method="post">
                        <div class="form-group">
                            <label for="subject_sno">Subject S.No. (Based on Syllabus/Curriculum)</label>
                            <select name="subject_sno" id="subject_sno" class="form-select" required>
                                <option value="">--Select Subject S.No.--</option>
                                <?php
                                for ($i = 1; $i < 13; $i++) {
                                    echo '<option value="' . $i . '">' . $i . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="subcode">Subject Code:</label>
                            <input type="text" name="subcode" id="subcode" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_fullname">Subject Full Name:</label>
                            <input type="text" name="sub_fullname" id="sub_fullname" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_shortname">Subject Short Name:</label>
                            <input type="text" name="sub_shortname" id="sub_shortname" class="form-control" maxlength="10" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_type">Subject Type:</label>
                            <select name="sub_type" id="sub_type" class="form-select" required>
                                <option value="Theory">Theory</option>
                                <option value="Lab">Lab</option>
                                <option value="PE">Professional Elective</option>
                                <option value="OE">Open Elective</option>
                                <option value="OE">Humanities Elective</option>
                                <option value="Skill">Skill Oriented Course</option>
                                <option value="MNCC">Mandatory Not-Credit Course</option>
                                <option value="dti">Design Thinking & Innovation</option>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                        <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                        <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Add Subject</button>
                    </form>
                    <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card">
                <div class="card-header">Important points</div>
                <div class="card-body">
                    <ul>
                        <li>The <u>Subject S.No</u>, <u>Subject Code</u> and <u>Full Name</u> should match the S.No, SubCode and Subject Name used in the <strong>Academic Syllabus</strong>.<br />(<strong>Same S.No</strong> will be present for the elective subjects)</li>
                        <br />
                        <li>If there is any Lab / Subject divided into groups, Add Same subject multiple times. For example:
                            <ul>
                                <li>Suppose a <strong>Python Programming Lab (Subcode: ABC123)</strong> is divided into two groups in a class of 60 students. Group A has students 1-30, and Group B has students 31-60.</li>
                                <li>In this case, the subject code and name will be added twice:
                                    <ul>
                                        <li><strong>ABC123a: Python Programming Lab (Group A)</strong></li>
                                        <li><strong>ABC123b: Python Programming Lab (Group B)</strong></li>
                                    </ul>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <br>
    <div class="card">
        <div class="card-header">Display Subjects</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr style='border-top: 1px solid #000;'>
                        <th>S.No.</th>
                        <th>Code</th>
                        <th>Short Name</th>
                        <th>Full Name</th>
                        <th>Type</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($subjectList['status']) && $subjectList['status'] == 1) {
                        $sno = "";
                        foreach ($subjectList['data'] as $subject) {
                            echo '<tr>';
                            if ($sno == $subject['subject_sno']) {
                                $style = '';
                            } else {
                                $style = " style= 'border-top: 1px solid #000;'";
                                $sno = $subject['subject_sno'];
                            }
                            echo "<td {$style}>{$subject['subject_sno']}</td>";
                            echo "<td {$style}>{$subject['subcode']}</td>";                            
                            echo "<td {$style}>{$subject['sub_shortname']}</td>";
                            echo "<td {$style}>{$subject['sub_fullname']}</td>";
                            echo "<td {$style}>{$subject['sub_type']}</td>";
                            echo "<td {$style}>";
                    ?>
                            <form action="hodviewsubjects.php" method="post" onsubmit="return confirm('Are you sure you want to Delete Subject:  <?= htmlspecialchars($subject['subcode'] ?? ''); ?> ?');">
                                <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                                <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                                <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $subject["id"]; ?>" />
                                <input type="hidden" name="whattodo" value="deletesubject" />
                                <input type="submit" class="btn btn-outline-danger" value="X" />
                            </form>
                    <?php
                            echo "</td>";
                            echo "<td {$style}>";                                                
                            echo "<button type='button' class='btn btn-primary btn-sm ml-2' onclick='editSubcode({$subject['id']}, \"{$subject['subcode']}\")'>
                                <i class='fa fa-edit'></i> Edit SubCode</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No subjects found.</td></tr>";
                    }
                    ?>
                </tbody>
                <script>
                    function editSubcode(subjectId, currentSubcode) {
                        var newSubcode = prompt("Edit Subject Code:", currentSubcode);

                        if (newSubcode !== null && newSubcode.trim() !== "" && newSubcode !== currentSubcode) {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';

                            var whattodoInput = document.createElement('input');
                            whattodoInput.type = 'hidden';
                            whattodoInput.name = 'whattodo';
                            whattodoInput.value = 'editsubcode';
                            form.appendChild(whattodoInput);

                            var subjectIdInput = document.createElement('input');
                            subjectIdInput.type = 'hidden';
                            subjectIdInput.name = 'subject_id';
                            subjectIdInput.value = subjectId;
                            form.appendChild(subjectIdInput);

                            var subcodeInput = document.createElement('input');
                            subcodeInput.type = 'hidden';
                            subcodeInput.name = 'new_subcode';
                            subcodeInput.value = newSubcode.trim();
                            form.appendChild(subcodeInput);

                            var secretcodeInput = document.createElement('input');
                            secretcodeInput.type = 'hidden';
                            secretcodeInput.name = 'secretcode';
                            secretcodeInput.value = '<?php echo $_SESSION['secretcode']; ?>';
                            form.appendChild(secretcodeInput);

                            // Add class_id to maintain context
                            var classIdInput = document.createElement('input');
                            classIdInput.type = 'hidden';
                            classIdInput.name = 'class_id';
                            classIdInput.value = '<?php echo $class_id; ?>';
                            form.appendChild(classIdInput);

                            document.body.appendChild(form);
                            form.submit();
                        }
                    }
                </script>

            </table>
        </div>
    </div>
</div>

<?php
require_once("hodfooter.php");
?>