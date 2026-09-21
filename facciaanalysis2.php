<?php
session_start();
// Assuming faculty.class.php and cia.class.php are included via autoloader or header
require_once("faculty.class.php"); // Make sure path is correct
require_once("cia.class.php");      // Make sure path is correct
require_once("facciaanalysis2.class.php"); // Include the updated class file

$facultyObj = new Faculty();
$ciaObj = new CIA(); // Assuming CIA class is needed for other context, otherwise remove
$analysisHelper = new COAnalysis(); // Instantiate analysis class for potential non-AJAX use later

$faculty_id = $_SESSION['facid'] ?? null; // Use null coalescing for safety

// Redirect if faculty ID is not set
if (!$faculty_id) {
    header("Location: ./"); // Redirect to login or appropriate page
    exit;
}

$facultySubjectsResult = $facultyObj->getSubjectsByFacultyId($faculty_id);
$facultySubjects = $facultySubjectsResult['status'] === 1 ? $facultySubjectsResult['data'] : [];

$selected_sub_id = null;
$selected_assessment_number = null;
$subject_details_header = "Select Subject"; // Default header

// Handle POST request for subject and assessment selection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic CSRF check example (enhance as needed)
    if (!empty($_POST['secretcode']) && isset($_SESSION['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']); // Use the token once

        $selected_sub_id = filter_input(INPUT_POST, 'sub_id', FILTER_VALIDATE_INT);

        // Assessment number can be an integer or 'all'
        $assessment_input = isset($_POST['assessment_number']) ? htmlspecialchars(trim($_POST['assessment_number']), ENT_QUOTES, 'UTF-8') : null;
        if ($assessment_input === 'all' || filter_var($assessment_input, FILTER_VALIDATE_INT)) {
            $selected_assessment_number = $assessment_input;
        }
    } else {
        $_SESSION["err"] = "Form submission error. Please try again.";
        // Optionally clear selection on error
        $selected_sub_id = null;
        $selected_assessment_number = null;
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Handle GET request potentially carrying selection (e.g., from refresh or link)
    $selected_sub_id = filter_input(INPUT_GET, 'sub_id', FILTER_VALIDATE_INT);
    $assessment_input = isset($_GET['assessment_number']) ? htmlspecialchars(trim($_GET['assessment_number']), ENT_QUOTES, 'UTF-8') : null;
    if ($assessment_input === 'all' || filter_var($assessment_input, FILTER_VALIDATE_INT)) {
        $selected_assessment_number = $assessment_input;
    } else {
        $selected_assessment_number = null; // Default to nothing if invalid assessment in GET
    }
    // Ensure selected subject is valid for the faculty
    if ($selected_sub_id && !empty($facultySubjects)) {
        $isValidSubject = false;
        foreach ($facultySubjects as $subject) {
            if ($subject['id'] == $selected_sub_id) {
                $isValidSubject = true;
                break;
            }
        }
        if (!$isValidSubject) {
            $selected_sub_id = null; // Reset if not a valid subject for this faculty
            $selected_assessment_number = null;
            $_SESSION["err"] = "Invalid subject selected.";
        }
    }
}


// Generate a new secret code for CSRF protection
$_SESSION['secretcode'] = bin2hex(random_bytes(32)); // More secure random token

// Get Subject Details for Header & Subject Type
$selected_sub_type = 'theory';
if ($selected_sub_id && !empty($facultySubjects)) {
    foreach ($facultySubjects as $subject) {
        if ($subject['id'] == $selected_sub_id) {
            $selected_sub_type = strtolower($subject['sub_type'] ?? 'theory');
            $subject_details_header = "{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']}";
            break;
        }
    }
}

// For Lab subjects, there is only 1 CIA, so default to 1 if not set or invalid
if ($selected_sub_id && ($selected_sub_type === 'lab' || $selected_sub_type === 'dti')) {
    if (empty($selected_assessment_number) || $selected_assessment_number == '2') {
        $selected_assessment_number = '1';
    }
}


// Include header file AFTER setting session variables and processing POST/GET
$page_title = "CIA Analysis Dashboard";
require_once("facheader.php"); // Make sure path is correct

?>

<div class="container mt-4">

    <div class="card mb-4">
        <div class="card-header bg-primary-subtle py-2">
            <h6 class="mb-0">Select Course and Assessment for Analysis</h6>
        </div>
        <div class="card-body">
            <form action="facciaanalysis2.php" method="post" id="analysisSelectionForm" class="row g-3 align-items-end">
                <div class="col-md-6">
                    <label for="sub_id" class="form-label">Subject:</label>
                    <select name="sub_id" id="sub_id" class="form-select" required onchange="document.getElementById('analysisSelectionForm').submit();">
                        <option value="">-- Select Subject --</option>
                        <?php if (!empty($facultySubjects)): ?>
                            <?php foreach ($facultySubjects as $subject): ?>
                                <?php $selected = ($selected_sub_id == $subject['id']) ? 'selected' : ''; ?>
                                <option value="<?php echo htmlspecialchars($subject['id']); ?>" <?php echo $selected; ?>>
                                    <?php echo htmlspecialchars("{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']} - {$subject['acad_year']}"); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <option value="" disabled>No subjects assigned</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label for="assessment_number" class="form-label">Assessment:</label>
                    <select name="assessment_number" id="assessment_number" class="form-select" <?php echo !$selected_sub_id ? 'disabled' : 'required'; ?>>
                        <option value="">-- Select Assessment --</option>
                        <?php if ($selected_sub_id): // Only enable if subject is selected 
                        ?>
                            <?php
                            if ($selected_sub_type === 'lab' || $selected_sub_type === 'dti') {
                                $assessments = [1 => "Lab CIA"];
                            } else {
                                $assessments = [1 => "CIA 1", 2 => "CIA 2", 'all' => "Overall CIA"];
                            }
                            foreach ($assessments as $num => $label):
                                $selected = ($selected_assessment_number == $num) ? 'selected' : '';
                            ?>
                                <option value="<?php echo htmlspecialchars($num); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <button type="submit" class="btn btn-primary w-100">Load Analysis</button>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($_SESSION["succ"])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION["succ"]);
            unset($_SESSION["succ"]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (!empty($_SESSION["err"])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($_SESSION["err"]);
            unset($_SESSION["err"]); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>


    <?php if ($selected_sub_id && $selected_assessment_number): ?>

        <?php 
        require_once("ciaanalysis2_common_chart.php");
        echo renderAnalysisAssets(); // Include necessary JS libraries and global scripts
        echo renderAnalysisScripts($selected_sub_id, $selected_assessment_number); // Include the analysis scripts
        ?>


    <?php endif; // End check for selected_sub_id && selected_assessment_number 
    ?>

</div> <?php
        require_once("facfooter.php"); // Make sure path is correct
        ?>