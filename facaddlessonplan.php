<?php
session_start();
$page_title = "Lesson Plan (Estimated Delivery Schedule)";
require_once("facheader.php");
require_once("faculty.class.php");
require_once("courseoutcome.class.php");
require_once("services/LessonPlanService.php");

$facultyObj = new Faculty();
$coObj = new CourseOutcome();
$lpService = \Services\LessonPlanService::getInstance();

$faculty_id = $_SESSION['facid'] ?? 0;
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = !empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : (!empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : null);

$msg = '';
$err = '';

// Handle CSV Template Download
if (isset($_GET['download_template']) && $selected_sub_id) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lesson_plan_template_' . $selected_sub_id . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Unit (1-5)', 'Lecture No (1-50)', 'Planned Topic Description', 'Target CO Number (1-6)', 'Bloom Level (L1-L6)', 'Pedagogy', 'Reference Material', 'Planned Hours']);
    fputcsv($out, ['1', '1', 'Introduction to Database Systems & Architecture', '1', 'L1-Remember', 'Chalk & Talk', 'T1: Ch 1.1-1.3', '1']);
    fputcsv($out, ['1', '2', 'Data Models and Schemas', '1', 'L2-Understand', 'PPT/LCD', 'T1: Ch 1.4-1.6', '1']);
    fclose($out);
    exit;
}

// Handle Single Lecture Plan Addition
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lecture']) && $selected_sub_id) {
    $data = [
        'sub_id' => $selected_sub_id,
        'unit_number' => (int)($_POST['unit_number'] ?? 1),
        'lecture_number' => (int)($_POST['lecture_number'] ?? 1),
        'planned_topic' => trim($_POST['planned_topic'] ?? ''),
        'co_id' => (int)($_POST['co_id'] ?? 0),
        'bloom_level' => trim($_POST['bloom_level'] ?? 'L3-Apply'),
        'pedagogy' => trim($_POST['pedagogy'] ?? 'Chalk & Talk'),
        'reference_material' => trim($_POST['reference_material'] ?? ''),
        'planned_hours' => (int)($_POST['planned_hours'] ?? 1)
    ];

    if (!empty($data['planned_topic']) && $data['co_id'] > 0) {
        $res = $lpService->addOrUpdateLecturePlan($data);
        if ($res['status'] == 1) {
            $msg = "Lecture #" . $data['lecture_number'] . " saved successfully.";
        } else {
            $err = $res['error'] ?? "Failed to save lecture plan.";
        }
    } else {
        $err = "Topic description and target Course Outcome are required.";
    }
}

// Handle Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_lecture_id']) && $selected_sub_id) {
    $delId = (int)$_POST['delete_lecture_id'];
    $delRes = $lpService->deleteLecturePlan($delId, $selected_sub_id);
    if ($delRes['status'] == 1) {
        $msg = "Lecture plan entry deleted.";
    } else {
        $err = $delRes['error'] ?? "Failed to delete entry.";
    }
}

// Handle Bulk CSV Upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_csv']) && $selected_sub_id) {
    if (!empty($_FILES['csv_file']['tmp_name']) && is_uploaded_file($_FILES['csv_file']['tmp_name'])) {
        $cosRes = $coObj->getCOsBySubjectId($selected_sub_id);
        $coNumToId = [];
        foreach ($cosRes['data'] as $co) {
            $coNumToId[(int)$co['co_number']] = (int)$co['id'];
        }

        $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
        fgetcsv($file); // Skip header row
        $importedCount = 0;

        while (($row = fgetcsv($file, 1000, ',')) !== false) {
            if (empty($row[0]) || empty($row[1]) || empty($row[2])) continue;
            $unit = (int)$row[0];
            $lecNo = (int)$row[1];
            $topic = trim($row[2]);
            $coNum = (int)($row[3] ?? 1);
            $bloom = !empty($row[4]) ? trim($row[4]) : 'L3-Apply';
            $pedagogy = !empty($row[5]) ? trim($row[5]) : 'Chalk & Talk';
            $ref = !empty($row[6]) ? trim($row[6]) : '';
            $hours = !empty($row[7]) ? (int)$row[7] : 1;

            $coId = $coNumToId[$coNum] ?? (reset($coNumToId) ?: 0);
            if ($coId > 0 && !empty($topic)) {
                $lpService->addOrUpdateLecturePlan([
                    'sub_id' => $selected_sub_id,
                    'unit_number' => $unit,
                    'lecture_number' => $lecNo,
                    'planned_topic' => $topic,
                    'co_id' => $coId,
                    'bloom_level' => $bloom,
                    'pedagogy' => $pedagogy,
                    'reference_material' => $ref,
                    'planned_hours' => $hours
                ]);
                $importedCount++;
            }
        }
        fclose($file);
        $msg = "Bulk upload successful: $importedCount lectures imported.";
    } else {
        $err = "Please select a valid CSV file.";
    }
}

// Load Subject Data if selected
$subjectDetails = null;
$lessonPlan = [];
$subjectCOs = [];
$varianceStats = null;

if ($selected_sub_id) {
    $subjectDetails = $facultyObj->getSubjectDetails($selected_sub_id);
    $lessonPlan = $lpService->getPlanBySubject($selected_sub_id);
    $coRes = $coObj->getCOsBySubjectId($selected_sub_id);
    $subjectCOs = $coRes['data'] ?? [];
    $varianceStats = $lpService->getSyllabusVariance($selected_sub_id);
}

require_once("facmenu.php");
?>

<div class="container my-4">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Course Delivery Plan / Lesson Plan (Estimated Diary)</h5>
            <?php if ($selected_sub_id): ?>
                <a href="facaddattendance.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary">
                    <i class="bi bi-clipboard-check me-1"></i>Mark Attendance
                </a>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="facaddlessonplan.php" method="get" class="row g-3 align-items-end mb-3">
                <div class="col-md-9">
                    <label for="sub_id" class="form-label fw-bold">Select Course / Subject Offering:</label>
                    <select name="sub_id" id="sub_id" class="form-select" onchange="this.form.submit()">
                        <option value="">-- Choose Subject --</option>
                        <?php foreach ($facultySubjects['data'] as $sub): ?>
                            <option value="<?= $sub['id'] ?>" <?= ($selected_sub_id == $sub['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($sub['sub_fullname']) ?> (<?= htmlspecialchars($sub['subcode']) ?>) - <?= htmlspecialchars($sub['class_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Load Plan</button>
                </div>
            </form>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_sub_id): ?>
        <!-- Pacing & Variance Card -->
        <?php if (!empty($varianceStats)): ?>
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card border-primary text-center p-3">
                        <h6 class="text-muted mb-1">Planned Lectures</h6>
                        <h3 class="text-primary mb-0"><?= $varianceStats['total_planned_lectures'] ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-success text-center p-3">
                        <h6 class="text-muted mb-1">Conducted Periods</h6>
                        <h3 class="text-success mb-0"><?= $varianceStats['actual_conducted_lectures'] ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-info text-center p-3">
                        <h6 class="text-muted mb-1">Syllabus Completion</h6>
                        <h3 class="text-info mb-0"><?= $varianceStats['completion_percentage'] ?>%</h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-warning text-center p-3">
                        <h6 class="text-muted mb-1">COs Covered in Diary</h6>
                        <h3 class="text-warning mb-0"><?= $varianceStats['cos_covered_count'] ?> / <?= count($subjectCOs) ?></h3>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Action Tools: Single Add + Bulk Upload -->
        <div class="row mb-4">
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light fw-bold"><i class="bi bi-plus-circle me-1"></i>Add / Edit Lecture Plan Entry</div>
                    <div class="card-body">
                        <?php if (empty($subjectCOs)): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-info-circle me-1"></i>No Course Outcomes configured yet. Please configure COs in <a href="facaddcos.php?sub_id=<?= $selected_sub_id ?>" class="alert-link">Course Outcomes Setup</a> first.
                            </div>
                        <?php else: ?>
                            <form action="facaddlessonplan.php" method="post" class="row g-2">
                                <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Unit # (1-5):</label>
                                    <select name="unit_number" class="form-select form-select-sm" required>
                                        <?php for ($u = 1; $u <= 5; $u++): ?>
                                            <option value="<?= $u ?>">Unit <?= $u ?></option>
                                        <?php endfor; ?>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">Lecture #:</label>
                                    <input type="number" name="lecture_number" class="form-control form-control-sm" min="1" max="100" value="<?= count($lessonPlan) + 1 ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-bold">Target Course Outcome:</label>
                                    <select name="co_id" class="form-select form-select-sm" required>
                                        <?php foreach ($subjectCOs as $co): ?>
                                            <option value="<?= $co['id'] ?>">CO<?= $co['co_number'] ?> - <?= htmlspecialchars(substr($co['co_description'], 0, 45)) ?>...</option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label small fw-bold">Planned Syllabus Topic / Concept:</label>
                                    <input type="text" name="planned_topic" class="form-control form-control-sm" placeholder="e.g. Relational Algebra operations, SQL Joins" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Bloom's Level:</label>
                                    <select name="bloom_level" class="form-select form-select-sm">
                                        <option value="L1-Remember">L1 - Remember</option>
                                        <option value="L2-Understand">L2 - Understand</option>
                                        <option value="L3-Apply" selected>L3 - Apply</option>
                                        <option value="L4-Analyze">L4 - Analyze</option>
                                        <option value="L5-Evaluate">L5 - Evaluate</option>
                                        <option value="L6-Create">L6 - Create</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Teaching Pedagogy:</label>
                                    <select name="pedagogy" class="form-select form-select-sm">
                                        <option value="Chalk & Talk" selected>Chalk & Talk</option>
                                        <option value="PPT/LCD">PPT / Projector</option>
                                        <option value="Coding Demo">Live Coding Demo</option>
                                        <option value="Group Discussion">Group Discussion</option>
                                        <option value="Flipped Classroom">Flipped Classroom</option>
                                        <option value="Video/Animation">Video / Animation</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Reference Material:</label>
                                    <input type="text" name="reference_material" class="form-control form-control-sm" placeholder="e.g. T1: Ch 2.3, R1: Sec 4">
                                </div>
                                <div class="col-12 mt-3">
                                    <button type="submit" name="save_lecture" class="btn btn-sm btn-primary w-100">
                                        <i class="bi bi-save me-1"></i>Save Lecture Plan
                                    </button>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Bulk CSV Import -->
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-light fw-bold"><i class="bi bi-file-earmark-spreadsheet me-1"></i>Bulk Excel / CSV Import</div>
                    <div class="card-body">
                        <p class="small text-muted mb-2">Import pre-formatted lecture plans for the entire semester (45-50 lectures) in one upload.</p>
                        <a href="facaddlessonplan.php?sub_id=<?= $selected_sub_id ?>&download_template=1" class="btn btn-sm btn-outline-success mb-3 w-100">
                            <i class="bi bi-download me-1"></i>Download CSV Template
                        </a>
                        <form action="facaddlessonplan.php" method="post" enctype="multipart/form-data">
                            <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                            <div class="mb-3">
                                <label class="form-label small fw-bold">Upload Completed CSV:</label>
                                <input type="file" name="csv_file" class="form-control form-control-sm" accept=".csv" required>
                            </div>
                            <button type="submit" name="upload_csv" class="btn btn-sm btn-success w-100">
                                <i class="bi bi-upload me-1"></i>Upload & Populate Plan
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Lesson Plan Table -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-list-ol me-1"></i>Scheduled Course Delivery Plan (<?= count($lessonPlan) ?> Lectures)</span>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered table-hover table-striped align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th style="width: 50px;">Lec #</th>
                            <th style="width: 70px;">Unit</th>
                            <th>Planned Topic Description</th>
                            <th style="width: 80px;">CO</th>
                            <th style="width: 110px;">Bloom</th>
                            <th style="width: 120px;">Pedagogy</th>
                            <th>Reference</th>
                            <th style="width: 60px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($lessonPlan)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-calendar-x fs-3 d-block mb-1"></i>
                                    No lesson delivery schedule entered yet. Add lectures above or use the CSV import.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($lessonPlan as $lp): ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $lp['lecture_number'] ?></td>
                                    <td class="text-center"><span class="badge bg-secondary">Unit <?= $lp['unit_number'] ?></span></td>
                                    <td><?= htmlspecialchars($lp['planned_topic']) ?></td>
                                    <td class="text-center"><span class="badge bg-primary">CO<?= $lp['co_number'] ?></span></td>
                                    <td><span class="badge bg-info text-dark"><?= htmlspecialchars($lp['bloom_level']) ?></span></td>
                                    <td><small><?= htmlspecialchars($lp['pedagogy']) ?></small></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($lp['reference_material'] ?? '-') ?></small></td>
                                    <td class="text-center">
                                        <form action="facaddlessonplan.php" method="post" onsubmit="return confirm('Delete Lecture #<?= $lp['lecture_number'] ?>?');">
                                            <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                                            <input type="hidden" name="delete_lecture_id" value="<?= $lp['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger p-1" title="Delete Lecture"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once("footer.php"); ?>
