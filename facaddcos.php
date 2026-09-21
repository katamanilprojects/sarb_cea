<?php
session_start();
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = null;

// Handle subject selection
if (!empty($_POST['sub_id'])) {
    $selected_sub_id = $_POST['sub_id'];
}

if (isset($_POST['submit_cos'])) {
    foreach ($_POST as $k => $v) {
        $a = substr($k, 0, 2);
        $b = substr($k, 2);
        $v = trim($v);
        if ($a == "co") {
            $ciaObj->addCO($selected_sub_id, $b, $v);
        }
    }
}

// Handle CO insertion
if (!empty($_POST['add_co']) && !empty($_POST['co_number']) && !empty($_POST['co_description']) && !empty($selected_sub_id)) {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        $co_description = trim($_POST['co_description']);
        $co_number = trim($_POST['co_number']);
        $ciaObj->addCO($selected_sub_id, $co_number, $co_description);
        $_SESSION['succ'] = "CO added successfully.";
    } else {
        $_SESSION['err'] = "Invalid request. Please try again.";
    }
}

// Handle Questionnaire Question insertion (New functionality)
if (isset($_POST['submit_qn_question']) && !empty($selected_sub_id)) {
    // Validate secret code for form submission
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']); // Consume secret code
        $question_number = trim($_POST['qn_number']);
        $question_text = trim($_POST['qn_text']);
        $created_by_user_id = $_SESSION['userid']; // Get user ID from session
        $created_by_role = $_SESSION['role']; // Get user role from session

        if (!empty($question_number) && !empty($question_text)) {
            // Assuming addSubjectQuestionnaireQuestion method exists and works in Faculty.class.php
            $add_qn_result = $facultyObj->addSubjectQuestionnaireQuestion($selected_sub_id, $question_number, $question_text, $created_by_user_id, $created_by_role);
            if ($add_qn_result['status'] == 1) {
                $_SESSION['succ'] = ($_SESSION['succ'] ?? '') . "Questionnaire question added successfully.";
            } else {
                $_SESSION['err'] = ($_SESSION['err'] ?? '') . "Failed to add questionnaire question: " . ($add_qn_result['err'] ?? 'Unknown error');
            }
        } else {
            $_SESSION['err'] = ($_SESSION['err'] ?? '') . "Question number and text cannot be empty.";
        }
    } else {
        $_SESSION['err'] = "Invalid request. Please try again.";
    }
    // No redirection here to show messages on the same page, messages will be displayed on reload.
}

// Fetch existing COs
$courseOutcomes = $selected_sub_id ? $ciaObj->getCOsBySubjectId($selected_sub_id) : [];
$questionnaireQuestions = $selected_sub_id ? $facultyObj->getSubjectQuestionnaireQuestions($selected_sub_id) : [];

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("facheader.php");

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Select Subject</div>
                <div class="card-body">
                    <form action="facaddcos.php" method="post">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                foreach ($facultySubjects['data'] as $subject) {
                                    $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                    $subjectid = htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8');
                                    $sub_fullname = htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8');
                                    $subcode = htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8');
                                    $class_name = htmlspecialchars($subject['class_name'], ENT_QUOTES, 'UTF-8');
                                    $acad_year = htmlspecialchars($subject['acad_year'], ENT_QUOTES, 'UTF-8');
                                    echo "<option value='$subjectid' $selected>$sub_fullname ($subcode) - $class_name - $acad_year</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary">Get COs</button>
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
        <?php if (!empty($courseOutcomes['data'])) { ?>
            <br>
            <div class="card">
                <div class="card-header">Added Course Outcomes (COs)</div>
                <div class="card-body">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>CO No.</th>
                                <th>Course Outcome</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $co_num = 1;
                            foreach ($courseOutcomes['data'] as $index => $co) {
                                $co_num++;
                                echo "<tr><td>CO" . htmlspecialchars($co['co_number']) . "</td><td>" . htmlspecialchars($co['co_description']) . "</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <br>

            <div class="card" id="addnewBtnblock">
                <div class="card-body">
                    <button id="addnewBtn" class="btn btn-link">Click here to Add New/Missing CO</button>
                </div>
            </div>
            <div class="card" id="addnewblock" style="display: none;">
                <div class="card-header">Add New CO</div>
                <div class="card-body">
                    <form action="facaddcos.php" method="post">
                        <div class="form-group">
                            <label for="co_num">CO Number:</label>
                            <input type="text" name="co_num" id="co_num" class="form-control" readonly value="CO<?php echo $co_num; ?>" />
                        </div>

                        <div class="form-group">
                            <label for="co_description">CO Description:</label>
                            <input type="text" name="co_description" id="co_description" class="form-control" required>
                        </div>
                        <br>
                        <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                        <input type="hidden" name="co_number" value="<?php echo $co_num; ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <input type="submit" name="add_co" class="btn btn-success" value="Add CO" />
                    </form>
                </div>
            </div>

            <br>

            <div class="card">
                <div class="card-header">Additional Questionnaire Questions</div>
                <div class="card-body">
                    <?php if (!empty($questionnaireQuestions['data'])) { ?>
                        <h5>Added Questions</h5>
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Q No.</th>
                                    <th>Question</th>
                                    <th>Added By</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                foreach ($questionnaireQuestions['data'] as $qn) {
                                    echo "<tr><td>Q" . htmlspecialchars($qn['question_number']) . "</td><td>" . htmlspecialchars($qn['question_text']) . "</td><td>" . htmlspecialchars(ucfirst($qn['created_by_role'])) . "</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    <?php } else { ?>
                        <p>No additional questionnaire questions found for this subject.</p>
                    <?php } ?>

                    <div class="card mt-3" id="addnewQNBtnblock">
                        <div class="card-body">
                            <button id="addnewQNBtn" class="btn btn-link">Click here to Add New Question</button>
                        </div>
                    </div>
                    <div class="card mt-3" id="addnewQNblock" style="display: none;">
                        <div class="card-header">Add New Questionnaire Question</div>
                        <div class="card-body">
                            <form action="facaddcos.php" method="post">
                                <div class="form-group">
                                    <label for="qn_number">Question Number:</label>
                                    <?php
                                    // Determine the next Question number
                                    $next_qn_number = 1;
                                    if (!empty($questionnaireQuestions['data'])) {
                                        $last_qn = end($questionnaireQuestions['data']);
                                        $next_qn_number = $last_qn['question_number'] + 1;
                                    }
                                    ?>
                                    <input type="number" name="qn_number" id="qn_number" class="form-control" value="<?php echo $next_qn_number; ?>" required min="1" />
                                </div>

                                <div class="form-group">
                                    <label for="qn_text">Question Text:</label>
                                    <input type="text" name="qn_text" id="qn_text" class="form-control" required>
                                </div>
                                <br>
                                <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                <button type="submit" name="submit_qn_question" class="btn btn-success">Add Question</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php
        } else {
            echo '<br><div class="card">';
            echo '<div class="card-header">No COs Found for this Course..!</div>';
            echo '<div class="card-header">Lets Add Now</div>';

            echo '<div class="card-header" id="divnoofcos">How many COs are defined for this Course ? <input type="number" name="noofcos" id="noofcos" value="" required /><button type="button" name="getfields" id="getfields" class="btn btn-outline-primary">Ok</button></div>';
            echo '<div class="card-body"><form id="addcosform" method="post"></form></div>';
            echo "</div>";

            echo '<script>
                const getfields = document.getElementById("getfields");
                const noofcos = document.getElementById("noofcos");
                const divnoofcos = document.getElementById("divnoofcos");
                const addcosform = document.getElementById("addcosform");

                getfields.addEventListener("click", function() {
                    const numCOs = parseInt(noofcos.value); // Parse as integer
            
                    if (isNaN(numCOs) || numCOs <= 0) {
                        alert("Please enter a valid number of COs.");
                        return; // Stop execution if input is invalid
                    }
            
                    divnoofcos.style.display = "none";
                    addcosform.innerHTML = "<input type=\"hidden\" name=\"sub_id\" value=\"' . $selected_sub_id . '\">"; // Clear previous fields
            
                    for (let i = 1; i <= numCOs; i++) {
                        const div = document.createElement("div");
                        div.classList.add("form-group"); // Add Bootstrap classes for grouping
                        div.innerHTML = `<div class="input-group mb-3"><div class="input-group-prepend"><span class="input-group-text">CO${i}.</span></div>  
                                <input type="text" name="co${i}" id="co${i}" class="form-control" required>
                            </div></div>
                        `;
                        addcosform.appendChild(div);
                    }
            
                    // Add submit button
                    const submitButton = document.createElement("button");
                    submitButton.type = "submit";
                    submitButton.name = "submit_cos"; // Give it a name for server-side processing
                    submitButton.classList.add("btn", "btn-success", "mt-3");
                    submitButton.textContent = "Save COs";
                    addcosform.appendChild(submitButton);
            
                });
            </script>';
        }
        ?>
    <?php endif; ?>
    <script>
        const addnewQNBtn = document.getElementById("addnewQNBtn");
        const addnewQNBtnblock = document.getElementById("addnewQNBtnblock");
        const addnewQNblock = document.getElementById("addnewQNblock");

        if (addnewQNBtn) { // Check if the button exists
            addnewQNBtn.addEventListener("click", function() {
                addnewQNBtnblock.style.display = "none"; // Hide the button
                addnewQNblock.style.display = "block"; // Show the div
            });
        }
    </script>
</div>

<?php
require_once("facfooter.php");
?>