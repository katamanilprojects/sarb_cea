<?php
session_start();
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("courseoutcome.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$coObj = new CourseOutcome();
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
            $coObj->addCO($selected_sub_id, $b, $v);
        }
    }
}

// Handle BOS Master CO Import
if (!empty($_POST['import_bos_cos']) && !empty($selected_sub_id)) {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        $importRes = $coObj->importMasterCOsToSubject((int)$selected_sub_id);
        if ($importRes['status'] == 1 && $importRes['copied'] > 0) {
            $_SESSION['succ'] = "Successfully imported {$importRes['copied']} BOS Master Course Outcome(s) for this subject.";
        } elseif ($importRes['status'] == 1 && $importRes['copied'] == 0) {
            $_SESSION['succ'] = "BOS Master Course Outcomes are already imported or up-to-date.";
        } else {
            $_SESSION['err'] = "Failed to import BOS COs: " . ($importRes['error'] ?? 'No master COs defined for this syllabus.');
        }
    } else {
        $_SESSION['err'] = "Invalid request. Please try again.";
    }
}

// Handle CO insertion
if (!empty($_POST['add_co']) && !empty($_POST['co_number']) && !empty($_POST['co_description']) && !empty($selected_sub_id)) {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
        $co_description = trim($_POST['co_description']);
        $co_number = trim($_POST['co_number']);
        $bloom_level = !empty($_POST['bloom_level']) ? trim($_POST['bloom_level']) : 'L3-Apply';
        $target_threshold = !empty($_POST['target_threshold_percent']) ? (float)$_POST['target_threshold_percent'] : 60.0;
        $coObj->addCO($selected_sub_id, $co_number, $co_description, $bloom_level, $target_threshold);
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
$courseOutcomes = $selected_sub_id ? $coObj->getCOsBySubjectId($selected_sub_id) : [];
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
            <?php if (!empty($courseOutcomes['is_inherited'])) : ?>
                <div class="alert alert-info d-flex flex-wrap justify-content-between align-items-center mb-3">
                    <div>
                        <strong><i class="bi bi-info-circle-fill"></i> Inherited from BOS Curriculum Master Catalog:</strong>
                        <span>These COs are currently synchronized from the official Board of Studies syllabus template.</span>
                    </div>
                    <form method="post" action="facaddcos.php" class="my-1">
                        <input type="hidden" name="sub_id" value="<?php echo htmlspecialchars($selected_sub_id); ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="import_bos_cos" value="1" class="btn btn-warning btn-sm">
                            <i class="bi bi-download"></i> Import as Editable Subject COs
                        </button>
                    </form>
                </div>
            <?php else : ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-success"><i class="bi bi-check-circle"></i> Subject-Specific Course Outcomes</span>
                    <form method="post" action="facaddcos.php" onsubmit="return confirm('Re-importing will sync your COs with the master catalog template. Continue?');" class="mb-0">
                        <input type="hidden" name="sub_id" value="<?php echo htmlspecialchars($selected_sub_id); ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="import_bos_cos" value="1" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-repeat"></i> Re-sync from BOS Master Catalog
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header bg-light fw-bold">Added Course Outcomes (COs)</div>
                <div class="card-body">
                    <table class="table table-bordered table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 80px;">CO No.</th>
                                <th>Course Outcome</th>
                                <th style="width: 150px;">Bloom's Taxonomy</th>
                                <th style="width: 140px;">Target Threshold</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $max_co = 0;
                            foreach ($courseOutcomes['data'] as $index => $co) {
                                $max_co = max($max_co, (int)$co['co_number']);
                                $bloom = htmlspecialchars($co['bloom_level'] ?? 'L3-Apply');
                                $thresh = htmlspecialchars($co['target_threshold_percent'] ?? 60.0);
                                echo "<tr>";
                                echo "<td><span class='badge bg-secondary'>CO" . htmlspecialchars($co['co_number']) . "</span></td>";
                                echo "<td>" . htmlspecialchars($co['co_description']) . "</td>";
                                echo "<td><span class='badge bg-info text-dark'>" . $bloom . "</span></td>";
                                echo "<td><span class='badge bg-light text-dark border'>" . $thresh . "%</span></td>";
                                echo "</tr>";
                            }
                            $co_num = $max_co + 1;
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <br>

            <div class="card" id="addnewBtnblock">
                <div class="card-body">
                    <button id="addnewBtn" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle"></i> Click here to Add New/Missing CO</button>
                </div>
            </div>
            <div class="card" id="addnewblock" style="display: none;">
                <div class="card-header bg-light fw-bold">Add New Course Outcome (CO)</div>
                <div class="card-body">
                    <form action="facaddcos.php" method="post">
                        <div class="row">
                            <div class="col-md-3 form-group mb-3">
                                <label for="co_num" class="form-label fw-bold">CO Number:</label>
                                <input type="text" name="co_num" id="co_num" class="form-control" readonly value="CO<?php echo $co_num; ?>" />
                            </div>

                            <div class="col-md-5 form-group mb-3">
                                <label for="bloom_level" class="form-label fw-bold">Bloom's Taxonomy Level:</label>
                                <select name="bloom_level" id="bloom_level" class="form-select">
                                    <option value="L1-Remember">L1 - Remember</option>
                                    <option value="L2-Understand">L2 - Understand</option>
                                    <option value="L3-Apply" selected>L3 - Apply</option>
                                    <option value="L4-Analyze">L4 - Analyze</option>
                                    <option value="L5-Evaluate">L5 - Evaluate</option>
                                    <option value="L6-Create">L6 - Create</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label for="target_threshold_percent" class="form-label fw-bold">Target Threshold (%):</label>
                                <div class="input-group">
                                    <input type="number" step="0.1" name="target_threshold_percent" id="target_threshold_percent" class="form-control" value="60.0" min="1" max="100" required>
                                    <span class="input-group-text">%</span>
                                </div>
                            </div>

                            <div class="col-12 form-group mb-3">
                                <label for="co_description" class="form-label fw-bold">CO Description / Statement:</label>
                                <textarea name="co_description" id="co_description" class="form-control" rows="3" placeholder="At the end of the course, student will be able to..." required></textarea>
                            </div>
                        </div>

                        <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                        <input type="hidden" name="co_number" value="<?php echo $co_num; ?>">
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="add_co" value="1" class="btn btn-success"><i class="bi bi-check-lg"></i> Add CO</button>
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
            echo '<br>';
            echo '<div class="card mb-3 border-primary shadow-sm">';
            echo '  <div class="card-header bg-primary text-white fw-bold"><i class="bi bi-magic"></i> Option 1: Fast-Track Import from BOS Catalog</div>';
            echo '  <div class="card-body">';
            echo '    <p class="text-muted mb-3">If your Board of Studies (BOS) has already defined Course Outcomes in the curriculum syllabus catalog, you can import them into your subject offering instantly with one click.</p>';
            echo '    <form method="post" action="facaddcos.php">';
            echo '      <input type="hidden" name="sub_id" value="' . htmlspecialchars($selected_sub_id) . '">';
            echo '      <input type="hidden" name="secretcode" value="' . $_SESSION['secretcode'] . '">';
            echo '      <button type="submit" name="import_bos_cos" value="1" class="btn btn-primary"><i class="bi bi-box-arrow-in-down"></i> Import BOS Approved Course Outcomes</button>';
            echo '    </form>';
            echo '  </div>';
            echo '</div>';

            echo '<div class="card shadow-sm">';
            echo '<div class="card-header bg-light fw-bold">Option 2: Manually Define Course Outcomes</div>';
            echo '<div class="card-body">';
            echo '<div class="form-group mb-3" id="divnoofcos"><label class="form-label fw-bold">How many COs are defined for this Course?</label><div class="input-group" style="max-width: 320px;"><input type="number" class="form-control" name="noofcos" id="noofcos" min="1" max="10" placeholder="e.g. 5" required /><button type="button" name="getfields" id="getfields" class="btn btn-outline-primary">Generate Fields</button></div></div>';
            echo '<form id="addcosform" method="post"></form>';
            echo '</div>';
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
        // Fix for addnewBtn - show "Add New CO" form
        const addnewBtn = document.getElementById("addnewBtn");
        const addnewBtnblock = document.getElementById("addnewBtnblock");
        const addnewblock = document.getElementById("addnewblock");
        if (addnewBtn) {
            addnewBtn.addEventListener("click", function() {
                addnewBtnblock.style.display = "none";
                addnewblock.style.display = "block";
            });
        }

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