<?php
session_start();
$page_title = "Add";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

// Handle form submission
if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode'] && !empty($_POST['submit_action'])) {
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];
    $submitAction = $_POST["submit_action"];

    if ($submitAction == 'save') {
        // Save marks temporarily
        foreach ($_POST['student_id'] as $index => $studentId) {
            $subjectiveMarks = $_POST['subjective_marks'][$index];
            $objectiveMarks = $_POST['objective_marks'][$index];
            $assignmentMarks = $_POST['assignment_marks'][$index];

            // Insert or Update in temp table
            $ciaObj->saveTempMarks($studentId, $sub_id, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks);
        }
        $_SESSION["succ"] = "Student Continuous Internal Assessment Successfully Saved Temporarily";
        
    } elseif ($submitAction == 'submit') {

        $isValid = true;
        // VALIDATE marks for each student
        foreach ($_POST['student_id'] as $index => $studentId) {
            $subjectiveMarks = $_POST['subjective_marks'][$index];
            $objectiveMarks = $_POST['objective_marks'][$index];
            $assignmentMarks = $_POST['assignment_marks'][$index];

            // Validate marks (ensure they are within the allowed range)
            if ($subjectiveMarks > 15 || $subjectiveMarks < 0 || $objectiveMarks > 10 || $objectiveMarks < 0 || $assignmentMarks > 5 || $assignmentMarks < 0 || !is_numeric($subjectiveMarks) || !is_numeric($objectiveMarks) || !is_numeric($assignmentMarks)) {
                $_SESSION["err"] = "Invalid marks entered for one or more students. Please check and try again.";
                $isValid = false;
                break;
            }
        }

        if ($isValid) {
            // Save marks for each student
            foreach ($_POST['student_id'] as $index => $studentId) {
                $subjectiveMarks = $_POST['subjective_marks'][$index];
                $objectiveMarks = $_POST['objective_marks'][$index];
                $assignmentMarks = $_POST['assignment_marks'][$index];

                $ciaObj->addInternalAssessmentMarks($studentId, $sub_id, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks, $_SESSION['facid']);

                // Remove temporary marks
                $ciaObj->deleteTempMarks($studentId, $sub_id, $assessmentNumber);

            }
            $_SESSION["succ"] = "Student Continuous Internal Assessment Successfully Added";
        }
    }

    // Redirect back to facciamarks.php after saving
    header("Location: facciamarks.php");
    exit();
}

// Get subject details and student list
if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList = $facultyObj->getMappedStudents($sub_id);

    // Fetch temporary marks if available
    $tempMarks = $ciaObj->getTempMarks($sub_id, $assessmentNumber);
    // Prefill form with temp marks if available
    foreach ($studentList as $key => $student) {
        if (!empty($tempMarks[$student['id']])) {
            $studentList[$key]['subjective_marks'] = $tempMarks[$student['id']]['subjective_marks'];
            $studentList[$key]['objective_marks'] = $tempMarks[$student['id']]['objective_marks'];
            $studentList[$key]['assignment_marks'] = $tempMarks[$student['id']]['assignment_marks'];
        }
    }

    // Generate new secret code
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
} else {
    // Handle invalid request (e.g., redirect to an error page)
    header("Location: facciamarks.php");
    exit();
}

require_once("facheader.php");

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">
                    Adding Continuous Internal Assessment Marks - <?php echo $assessmentNumber; ?> 
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <form action="facaddciamarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Adm. No.<br />Student Name</th>
                                    <th>Assignment (Max 5)</th>
                                    <th>Objective (Max 10)</th>
                                    <th>Subjective (Max 15)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $key => $student) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['username'], ENT_QUOTES, 'UTF-8'); ?><br /><?php echo htmlspecialchars($student['name'], ENT_QUOTES, 'UTF-8'); ?><input type="hidden" name="student_id[]" value="<?php echo htmlspecialchars($student['id'], ENT_QUOTES, 'UTF-8'); ?>"></td>
                                        <td><input type="number" step="any" min="0" max="5" name="assignment_marks[]" class="form-control" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['assignment_marks'] : ''; ?>" required /></td>
                                        <td><input type="number" step="any" min="0" max="10" name="objective_marks[]" class="form-control" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['objective_marks'] : ''; ?>" required /></td>
                                        <td><input type="number" step="any" min="0" max="15" name="subjective_marks[]" class="form-control" value="<?php echo isset($tempMarks[$student['id']]) ? $tempMarks[$student['id']]['subjective_marks'] : ''; ?>" required /></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        </div>
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <div class="d-flex justify-content-between">
                            <button type="submit" name="submit_action" value="submit" class="pull-left btn btn-primary">Validate & Submit Marks</button>
                            <button type="submit" name="submit_action" value="save" class="text-end btn btn-secondary" formnovalidate>Save without Submitting</button>
                        </div>
                    </form>
                </div>
                <div class="card-footer">
                    <p class="text-muted">
                        <strong>Note:</strong>
                        <br>
                        The <strong>"Submit"</strong> button will finalize and lock your marks submission.
                        <br>
                        The <strong>"Save without Submitting"</strong> button will save your progress temporarily and you can continue later.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const form = document.querySelector('form');
        const submitButton = document.querySelector('button[name="submit_action"][value="submit"]');

        form.onsubmit = function(e) {
            // Only perform validation if the "Submit" button was clicked
            if (submitButton.clicked) {
                const subjectiveInputs = document.querySelectorAll('input[name="subjective_marks[]"]');
                const objectiveInputs = document.querySelectorAll('input[name="objective_marks[]"]');
                const assignmentInputs = document.querySelectorAll('input[name="assignment_marks[]"]');

                let isValid = true;
                let firstInvalidInput = null; // To store the first invalid input

                subjectiveInputs.forEach((input, index) => {
                    const subjectiveMarks = parseFloat(input.value);
                    const objectiveMarks = parseFloat(objectiveInputs[index].value);
                    const assignmentMarks = parseFloat(assignmentInputs[index].value);

                    // Validate if the inputs are filled and within the specified ranges
                    if (
                        isNaN(subjectiveMarks) || subjectiveMarks > 15 || subjectiveMarks < 0 ||
                        isNaN(objectiveMarks) || objectiveMarks > 10 || objectiveMarks < 0 ||
                        isNaN(assignmentMarks) || assignmentMarks > 5 || assignmentMarks < 0
                    ) {
                        isValid = false;

                        // Identify the first invalid input and focus on it
                        if (!firstInvalidInput) {
                            if (isNaN(assignmentMarks) || assignmentMarks > 5 || assignmentMarks < 0) {
                                firstInvalidInput = assignmentInputs[index];
                            } else if (isNaN(objectiveMarks) || objectiveMarks > 10 || objectiveMarks < 0) {
                                firstInvalidInput = objectiveInputs[index];
                            } else if (isNaN(subjectiveMarks) || subjectiveMarks > 15 || subjectiveMarks < 0) {
                                firstInvalidInput = input;
                            }
                        }

                        // Apply red border to invalid fields
                        if (isNaN(subjectiveMarks) || subjectiveMarks > 15 || subjectiveMarks < 0) {
                            input.style.border = "2px solid red"; // Highlight invalid subjective field
                        }else{
                            input.style.border = "";
                        }
                        if (isNaN(objectiveMarks) || objectiveMarks > 10 || objectiveMarks < 0) {
                            objectiveInputs[index].style.border = "2px solid red"; // Highlight invalid objective field
                        }else{
                            objectiveInputs[index].style.border = "";
                        }
                        if (isNaN(assignmentMarks) || assignmentMarks > 5 || assignmentMarks < 0) {
                            assignmentInputs[index].style.border = "2px solid red"; // Highlight invalid assignment field
                        }else{
                            assignmentInputs[index].style.border = "";
                        }
                    }else{
                        // Reset the border if valid
                        input.style.border = "";
                        objectiveInputs[index].style.border = "";
                        assignmentInputs[index].style.border = "";
                    }
                });

                if (!isValid) {
                    e.preventDefault(); // Prevent form submission if validation fails
                    alert("Please enter valid marks:\n* Subjective: 0-15\n* Objective: 0-10\n* Assignment: 0-5");

                    // Focus on the first invalid input and set the cursor there
                    if (firstInvalidInput) {
                        firstInvalidInput.focus();  // Focus on the first invalid input
                        setTimeout(() => {
                            firstInvalidInput.scrollIntoView({
                                behavior: "smooth",
                                block: "center",
                                inline: "nearest"
                            });
                        }, 100); // Ensure it's visible
                    }
                }
            }
        };

        // Track which button was clicked
        document.querySelectorAll('button[name="submit_action"]').forEach(button => {
            button.onclick = function() {
                submitButton.clicked = this.value === 'submit';
            };
        });
    });
</script>

<?php
require_once("facfooter.php");
?>