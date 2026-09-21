<?php
session_start();
require_once "faculty.class.php";
require_once "cia.class.php";

$page_title = "Edit";


if (!isset($_GET['sub_id']) || !isset($_GET['assessment_number']) || !isset($_GET['component_id']) || !isset($_GET['component_type'])) {
    die("Invalid request");
}

$sub_id = intval($_GET['sub_id']);
$assessment_number = $_GET['assessment_number'];
$component_id = intval($_GET['component_id']);
$component_type = $_GET['component_type'];


$facultyObj = new Faculty();
$cia = new CIA();
$questions = $cia->getQuestionsByComponent($component_id);
$cos = $cia->getCOsBySubjectId($sub_id);
$blooms_levels = $cia->getBloomsLevels();

$components = $cia->getAssessmentComponents($sub_id, $assessment_number);
$component_type = "";
if (!empty($components['data'])) {
    foreach ($components['data'] as $index => $component) {
        if ($component_id == $component['id']) {
            $component_type = $component['component_type'];
            break;
        }
    }
}

$isSubmitted = false;
$prg_res = $facultyObj->getPrgCodeBySubID($sub_id);
if ($prg_res['sub_type'] == "lab" || $prg_res['sub_type'] == "dti") {
    $isSubmitted = !empty($cia->isUGLabInternalAssessmentAdded($sub_id, $assessment_number));
} else {
    $isSubmitted = !empty($cia->isInternalAssessmentAdded($sub_id, $assessment_number));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_metadata'])) {
    if ($isSubmitted) {
        echo "<script>alert('Question paper meta data cannot be deleted after final submission.'); window.location.href='facquestionco.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
        exit;
    }

    $deleteMetaResult = $cia->deleteQuestionMetadataByComponent($component_id);
    if (!empty($deleteMetaResult['status'])) {
        echo "<script>alert('Question paper meta data deleted successfully.'); window.location.href='facciacomp.php?sub_id=$sub_id&assessment_number=$assessment_number';</script>";
        exit;
    } else {
        $deleteMsg = !empty($deleteMetaResult['err']) ? addslashes($deleteMetaResult['err']) : 'Failed to delete question paper meta data.';
        echo "<script>alert('" . $deleteMsg . "'); window.location.href='facquestionco.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
        exit;
    }
}


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_questions'])) {
    foreach ($_POST['questions'] as $index => $question) {
        $type = $question['type'];
        $marks = $question['marks'];
        $blooms_level = $question['blooms_level'];
        $selected_cos = isset($question['cos']) ? $question['cos'] : [];

        if ($type === 'Either-or') {
            // Each Either-or question is treated as two separate questions
            $labels = [$question['label_a'], $question['label_b']];
        } else {
            $labels = [$question['label']];
        }

        foreach ($labels as $label) {
            $question_id = $cia->addQuestion($component_id, $label, $type, $marks, $blooms_level);
            if ($question_id && count($selected_cos) > 0) {
                $cia->addQuestionCOs($question_id, $selected_cos);
            }
        }
    }
    echo "<script>alert('Questions added successfully!'); window.location.href='facquestionco.php?sub_id=$sub_id&assessment_number=$assessment_number&component_id=$component_id&component_type=$component_type';</script>";
}

$subjectDetails = $facultyObj->getSubjectDetails($sub_id);

require_once "facheader.php";
?>
<?php $url_get_data = "sub_id=" . $sub_id . "&assessment_number=" . $assessment_number; ?>

<div class="container mt-4">
    <form method="POST">
        <div class="card">
            <div class="card-header">
                <strong>Subject :</strong> <?php echo $subjectDetails['data']['sub_fullname']; ?> (<?php echo $subjectDetails['data']['subcode']; ?>)
            </div>
            <div class="card-header"> CIA - <?php echo $assessment_number; ?> <?php echo "(" . $component_type . ")"; ?> </div>
            <div class="card-header">
                Questions MetaData
            </div>
            <div class="card-body">

                <table class="table table-bordered" id="questions_table">
                    <thead>
                        <tr>
                            <?php if ($component_type == "Assignment"): ?>
                                <th>Assignment Type</th>
                            <?php else: ?>
                                <th>Q.No.</th>
                                <th>Question Type</th>
                            <?php endif; ?>
                            <th>Marks</th>
                            <th>Bloom’s Level</th>
                            <th>COs</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($questions)) {
                            foreach ($questions as $ques) {
                                echo '<tr>';
                                if ($component_type == "Assignment") {
                                    echo '<td>' . $ques['question_type'] . '</td>';
                                } else {
                                    echo '<td>' . $ques['question_label'] . '</td>';
                                    echo '<td>' . $ques['question_type'] . '</td>';
                                }
                                echo '<td>' . rtrim(rtrim(number_format(floatval($ques['marks']), 2, '.', ''), '0'), '.') . '</td>';
                                echo '<td>' . $ques['blooms_level_id'] . '</td>';
                                echo '<td>';
                                $cos_list = $cia->getCOsByQuestion($ques['id']);
                                if (!empty($cos_list)) {
                                    echo implode(', ', $cos_list);
                                } else {
                                    echo "N/A";
                                }
                                echo '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                        <!-- Questions will be dynamically added here -->
                    </tbody>
                </table>

            </div>
            <div class="card-footer">
                <a href="#" class="btn btn-link" id="addnewlink" onclick="showaddQuestions()"></a>
                <div class="mb-3" id="noofquesblock" style="display: none;">
                    <label for="num_questions">Lets Add Questions. Enter Number of Questions (to be added):</label>
                    <input type="number" id="num_questions" name="num_questions" class="form-control" min="1" required>
                    <button type="button" class="btn btn-primary mt-2" onclick="generateQuestions()">Generate</button>
                </div>
                <div id="savebtnblock" style="display: none;">
                    <button type="submit" name="submit_questions" class="btn btn-success mt-3">Save Questions</button>
                </div>
            </div>
            <div class="card-footer">
                <?php

                $marks = $cia->getMarksByComponent($component_id);

                // Define the logic for the Action Button
                if (!empty($marks)) {
                    // Marks exist: Go to View Marks
                    echo "<a class='float-end btn btn-success' href='facviewmarks.php?{$url_get_data}&component_id={$component_id}&component_type={$component_type}'>View Marks</a>";
                } else {
                    // No marks: Go to Marks Entry
                    echo "<a class='float-end btn btn-primary' href='facmarksentry.php?{$url_get_data}&component_id={$component_id}&component_type={$component_type}'>Next Step: Marks Entry</a>";
                }

                // The Delete Button (Always visible if not submitted)
                if (!$isSubmitted && !empty($questions)) {
                    // We use btn-outline-danger to make it look like a secondary choice
                    echo "<button type='button' class='btn btn-outline-danger' data-bs-toggle='modal' data-bs-target='#deleteMetaDataModal'>Delete Meta Data</button>";
                }
                ?>
            </div>
        </div>
    </form>
    <?php if (!$isSubmitted && !empty($questions)): ?>
        <div class="modal fade" id="deleteMetaDataModal" tabindex="-1" aria-labelledby="deleteMetaDataModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteMetaDataModalLabel">
                            <?php echo empty($marks) ? 'Confirm Deletion' : 'Action Restricted'; ?>
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body">
                        <?php if (empty($marks)): ?>
                            <p class="mb-0">Are you sure you want to delete the question meta data for this component? This action cannot be undone.</p>
                        <?php else: ?>
                            <div class="alert alert-warning mb-0">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                <strong>Notice:</strong> Question meta data cannot be deleted because marks have already been entered for this component.
                                <hr>
                                <p class="small mb-0">To proceed with deletion, you must first remove the marks from the <strong>View Marks</strong> section.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <?php echo empty($marks) ? 'Cancel' : 'Close'; ?>
                        </button>

                        <?php if (empty($marks)): ?>
                            <button type="submit" name="delete_metadata" class="btn btn-danger" form="deleteMetadataForm">Delete Meta Data</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" action="" id="deleteMetadataForm" class="d-none"></form>


</div>

<script>
    function showaddQuestions() {
        document.getElementById("noofquesblock").style.display = "block";
        document.getElementById("addnewlink").style.display = "none";
    }

    function generateQuestions() {
        let num = document.getElementById("num_questions").value;
        let container = document.getElementById("questions_table");
        let noofquesblock = document.getElementById("noofquesblock");
        let savebtnblock = document.getElementById("savebtnblock");

        container.innerHTML = ""; // Clear previous content

        let table = document.createElement("table");
        table.classList.add("table", "table-bordered");

        let thead = `
        <thead>
            <tr>
                <th>#</th>
                <th>Question Type</th>
                <th>Q.No.</th>
                <th>Marks</th>
                <th>Bloom’s Level</th>
                <th>COs</th>
            </tr>
        </thead>
    `;

        let tbody = "<tbody>";

        for (let i = 1; i <= num; i++) {
            tbody += `
            <tr>
                <td>${i}</td>
                <td>
                    <select name="questions[${i}][type]" class="form-select" onchange="toggleEitherOr(this, ${i})">
                        <option value="Either-or">Either-or</option>
                        <option value="MCQ">MCQ</option>
                        <option value="Blank">Blank</option>
                        <option value="Short_Answer">Short_Answer</option>
                        <option value="Essay">Essay</option>
                    </select>
                </td>
                <td id="question_label_${i}">
                    <input type="text" name="questions[${i}][label]" class="form-control" required>
                </td>
                <td>
                    <input type="number" name="questions[${i}][marks]" class="form-control" min=0 max=100 required>
                </td>
                <td>
                    <select name="questions[${i}][blooms_level]" class="form-select" required="required">
                        <option value="">--Level--</option>
                        <?php foreach ($blooms_levels as $b) { ?>
                            <option value="<?= $b['id'] ?>"><?= $b['blooms_label'] ?></option>
                        <?php } ?>
                    </select>
                </td>
                <td>
                    <?php foreach ($cos['data'] as $index => $co) { ?>
                        <input type="checkbox" name="questions[${i}][cos][]" value="<?= $co['co_number'] ?>"> CO<?= $co['co_number'] ?><br>
                    <?php } ?>
                </td>
            </tr>
        `;
        }

        tbody += "</tbody>";
        table.innerHTML = thead + tbody;
        container.appendChild(table);
        savebtnblock.style.display = "block";
        noofquesblock.style.display = "none";
    }

    function toggleEitherOr(select, index) {
        let cell = document.getElementById(`question_label_${index}`);

        if (!cell) {
            console.error(`Element with ID question_label_${index} not found.`);
            return;
        }

        if (select.value === "Either-or") {
            cell.innerHTML = `
            <input type="text" name="questions[${index}][label_a]" class="form-control" placeholder="Question A" required>
            <input type="text" name="questions[${index}][label_b]" class="form-control mt-2" placeholder="Question B" required>
        `;
        } else {
            cell.innerHTML = `
            <input type="text" name="questions[${index}][label]" class="form-control" required>
        `;
        }
    }
</script>

<?php require_once "facfooter.php"; ?>