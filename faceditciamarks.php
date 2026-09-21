<?php
session_start();
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();

// Handle form submission
if (!empty($_POST['sub_id']) && !empty($_POST['assessment_number']) && !empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    $sub_id = $_POST['sub_id'];
    $assessmentNumber = $_POST['assessment_number'];

    $isValid = true;
    // VALIDATE marks for each student
    foreach ($_POST['student_id'] as $index => $studentId) {
        $subjectiveMarks = $_POST['subjective_marks'][$index];
        $objectiveMarks = $_POST['objective_marks'][$index];
        $assignmentMarks = $_POST['assignment_marks'][$index];

        // Validate marks (ensure they are within the allowed range)
        if ($subjectiveMarks > 15 || $objectiveMarks > 10 || $assignmentMarks > 5 || !is_numeric($subjectiveMarks) || !is_numeric($objectiveMarks) || !is_numeric($assignmentMarks)) {
            // Handle invalid marks
            $_SESSION["err"] = "Invalid marks entered for one or more students. Please check and try again.";
            $isValid = false;
            break;
        }
    }

    if ($isValid) {
        // Update marks for each student
        foreach ($_POST['student_id'] as $index => $studentId) {
            $marksId = $_POST['marks_id'][$index]; 
            $subjectiveMarks = $_POST['subjective_marks'][$index];
            $objectiveMarks = $_POST['objective_marks'][$index];
            $assignmentMarks = $_POST['assignment_marks'][$index];

            $ciaObj->updateInternalAssessmentMarks($marksId, $subjectiveMarks, $objectiveMarks, $assignmentMarks);
        }
        $_SESSION["succ"] = "Student Continuous Internal Assessment Successfully Updated";
    }

    // Redirect back to facciamarks.php after saving
    header("Location: facciamarks.php?sub_id=$sub_id");
    exit();
}

// Get subject details and student list with marks
if (!empty($_GET['sub_id']) && !empty($_GET['assessment_number'])) {
    $sub_id = $_GET['sub_id'];
    $assessmentNumber = $_GET['assessment_number'];
    $subjectDetails = $facultyObj->getSubjectDetails($sub_id);
    $studentList1 = $facultyObj->getMappedStudents($sub_id);

    // Fetch marks for each student
    $studentList = []; 
    foreach ($studentList1 as $student) {
        $marks = $facultyObj->getStInternalAssessmentMarks($student['id'], $sub_id, $assessmentNumber);
        $student['marks'] = !empty($marks) ? $marks[0] : null;
        $studentList[] = $student; // Add the modified student data to the new array
    }

    // Generate new secret code
    $_SESSION['secretcode'] = bin2hex(random_bytes(32));
} else {
    // Handle invalid request
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
                    Editing Continuous Internal Assessment Marks - <?php echo $assessmentNumber; ?> 
                </div>
                <div class="card-header">
					<strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
                </div>
                <div class="card-body">
                    <form action="faceditciamarks.php" method="post">
                        <input type="hidden" name="sub_id" value="<?php echo $sub_id; ?>">
                        <input type="hidden" name="assessment_number" value="<?php echo $assessmentNumber; ?>">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th rowspan="2">Adm. No<br />Student Name</th>
                                    <th colspan="3" style="text-align: center;">CIA - <?php echo $assessmentNumber; ?></th>
                                </tr>
                                <tr>
                                    <th>Assignment<br>(5)</th>
                                    <th>Objective<br>(10)</th>
                                    <th>Subjective<br>(15)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentList as $student) :
                                    $subj = rtrim(rtrim(number_format($student['marks']['subjective_marks'], 1), '0'), '.');
                                    $obj = rtrim(rtrim(number_format($student['marks']['objective_marks'], 1), '0'), '.');
                                    $assgn = rtrim(rtrim(number_format($student['marks']['assignment_marks'], 1), '0'), '.');
                                    if(empty($subj)){
                                        $subj = 0;
                                    }
                                    if(empty($obj)){
                                        $obj = 0;
                                    }
                                    if(empty($assgn)){
                                        $assgn = 0;
                                    }
                                    ?>
                                    <tr>
                                        <td><?php echo $student['username']; ?><br /><?php echo $student['name']; ?></td>
                                        <td>
                                            <input type="text" name="assignment_marks[]" class="form-control" maxlength="5" value="<?php echo $assgn; ?>" required>
                                        </td>
                                        <td>
                                            <input type="text" name="objective_marks[]" class="form-control" maxlength="5" value="<?php echo $obj; ?>" required>
                                        </td>
                                        <td>
                                            <input type="hidden" name="student_id[]" value="<?php echo $student['id']; ?>">
                                            <input type="hidden" name="marks_id[]" value="<?php echo $student['marks'] ? $student['marks']['id'] : ''; ?>"> 
                                            <input type="text" name="subjective_marks[]" class="form-control" maxlength="5" value="<?php echo $subj; ?>" required>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" name="submit_action" value="submit" class="btn btn-primary">Validate & Update Marks</button>
                    </form>
                </div>
                <div class="card-footer">
                    <a href='facciamarks.php' class='btn btn-warning btn-sm'>Click here to Cancel Updates and Exit</a>                            
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