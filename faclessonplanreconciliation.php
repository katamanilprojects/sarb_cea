<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Authentication Guard
if (empty($_SESSION["user"]) || empty($_SESSION["role"]) || $_SESSION['role'] !== "faculty") {
    header('Location: ./');
    exit();
}

require_once("faculty.class.php");
require_once("services/LessonPlanService.php");

$facultyObj = new Faculty();
$lpService = \Services\LessonPlanService::getInstance();

$faculty_id = $_SESSION['facid'] ?? 0;
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = !empty($_GET['sub_id']) ? (int)$_GET['sub_id'] : (!empty($_POST['sub_id']) ? (int)$_POST['sub_id'] : null);

$msg = '';
$err = '';

// Handle Form Submission (Save Draft or Submit to HOD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_audit']) && $selected_sub_id) {
    $isFinal = !empty($_POST['is_final_submission']);
    $auditData = [
        'sub_id' => $selected_sub_id,
        'faculty_id' => $faculty_id,
        'total_planned_lectures' => (int)($_POST['total_planned_lectures'] ?? 0),
        'total_actual_conducted' => (int)($_POST['total_actual_conducted'] ?? 0),
        'total_compensatory_classes' => (int)($_POST['total_compensatory_classes'] ?? 0),
        'syllabus_completion_pct' => (float)($_POST['syllabus_completion_pct'] ?? 0.0),
        'unit1_completion_date' => !empty($_POST['unit1_completion_date']) ? $_POST['unit1_completion_date'] : null,
        'unit2_completion_date' => !empty($_POST['unit2_completion_date']) ? $_POST['unit2_completion_date'] : null,
        'unit3_completion_date' => !empty($_POST['unit3_completion_date']) ? $_POST['unit3_completion_date'] : null,
        'unit4_completion_date' => !empty($_POST['unit4_completion_date']) ? $_POST['unit4_completion_date'] : null,
        'unit5_completion_date' => !empty($_POST['unit5_completion_date']) ? $_POST['unit5_completion_date'] : null,
        'deviations_reason' => trim($_POST['deviations_reason'] ?? ''),
        'compensatory_actions' => trim($_POST['compensatory_actions'] ?? ''),
        'topics_beyond_syllabus' => trim($_POST['topics_beyond_syllabus'] ?? ''),
        'is_final_submission' => $isFinal
    ];

    $saveRes = $lpService->saveCourseCompletionAudit($auditData);
    if ($saveRes['status'] == 1) {
        $msg = $saveRes['message'];
    } else {
        $err = $saveRes['error'] ?? "Failed to save course completion audit.";
    }
}

// Load Subject & Reconciliation Data
$subjectDetails = null;
$recon = null;
if ($selected_sub_id) {
    $subjectDetails = $facultyObj->getSubjectDetails($selected_sub_id);
    $recon = $lpService->getReconciliationData($selected_sub_id);
}

$page_title = "Course Delivery Audit & Plan Reconciliation";
require_once("facheader.php");
?>

<div class="container my-4">
    <!-- Header / Selector Card -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-clipboard-data me-2"></i>End-of-Course Plan Reconciliation & Delivery Audit (NBA Criterion 2.2)</h5>
            <?php if ($selected_sub_id): ?>
                <div>
                    <a href="facaddlessonplan.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary me-1">
                        <i class="bi bi-calendar3 me-1"></i>Lesson Plan
                    </a>
                    <a href="download_bluebook.php?sub_id=<?= $selected_sub_id ?>" class="btn btn-sm btn-light text-primary">
                        <i class="bi bi-file-earmark-pdf me-1"></i>e-Bluebook
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="faclessonplanreconciliation.php" method="get" class="row g-3 align-items-end mb-2">
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
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-arrow-clockwise me-1"></i>Load Reconciliation</button>
                </div>
            </form>

            <?php if (!empty($msg)): ?>
                <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i><?= htmlspecialchars($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (!empty($err)): ?>
                <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($selected_sub_id && $recon): ?>
        <?php 
        $audit = $recon['audit'];
        $signoffStatus = $audit['faculty_signoff_status'] ?? 'DRAFT';
        ?>

        <!-- Audit Status Badge & Guidance -->
        <div class="alert <?= ($signoffStatus === 'SUBMITTED' || $signoffStatus === 'APPROVED') ? 'alert-success' : 'alert-info' ?> d-flex justify-content-between align-items-center mb-4">
            <div>
                <strong><i class="bi bi-info-circle me-1"></i>Course Completion Audit Status:</strong>
                <?php if ($signoffStatus === 'APPROVED'): ?>
                    <span class="badge bg-success ms-1">Approved by HOD</span>
                <?php elseif ($signoffStatus === 'SUBMITTED'): ?>
                    <span class="badge bg-primary ms-1">Submitted to HOD for Verification</span> (<?= date('d-m-Y H:i', strtotime($audit['faculty_signoff_at'])) ?>)
                <?php else: ?>
                    <span class="badge bg-warning text-dark ms-1">Draft (Not yet submitted)</span>
                <?php endif; ?>
            </div>
            <small class="text-muted">Supports NBA Criterion 2.1 & 2.2 / NAAC Criterion 2.3</small>
        </div>

        <!-- Metric Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card border-primary text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Planned Lectures</h6>
                    <h3 class="text-primary mb-0"><?= $recon['total_planned'] ?></h3>
                    <small class="text-muted">From Course Delivery Plan</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-success text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Conducted Periods</h6>
                    <h3 class="text-success mb-0"><?= $recon['total_conducted'] ?></h3>
                    <small class="text-muted">From Daily Class Diary</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-info text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Syllabus Completion</h6>
                    <h3 class="text-info mb-0"><?= $recon['completion_pct'] ?>%</h3>
                    <small class="text-muted"><?= ($recon['completion_pct'] >= 100) ? 'Full Coverage' : 'Deficit Identified' ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Compensatory Classes</h6>
                    <h3 class="text-warning mb-0"><?= $recon['compensatory_count'] ?></h3>
                    <small class="text-muted">Periods conducted beyond plan</small>
                </div>
            </div>
        </div>

        <!-- Side-by-Side Planned vs Actual Reconciliation Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="bi bi-arrow-left-right me-1"></i>Chronological Delivery Reconciliation (Planned Schedule vs Actual Diary)</span>
                <span class="badge bg-secondary"><?= count($recon['paired_rows']) ?> Total Entries</span>
            </div>
            <div class="table-responsive" style="max-height: 520px; overflow-y: auto;">
                <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size: 12.5px;">
                    <thead class="table-dark sticky-top">
                        <tr>
                            <th colspan="4" class="text-center bg-secondary border-end">WHAT WAS PLANNED (Lesson Plan)</th>
                            <th colspan="4" class="text-center bg-dark">WHAT WAS ACTUALLY DELIVERED (Teaching Diary)</th>
                        </tr>
                        <tr class="table-secondary text-dark" style="font-size: 11.5px;">
                            <th style="width: 45px;" class="text-center">Lec #</th>
                            <th style="width: 60px;" class="text-center">Unit</th>
                            <th>Planned Topic</th>
                            <th style="width: 55px;" class="text-center border-end">CO</th>
                            <th style="width: 90px;" class="text-center">Date & Hr</th>
                            <th>Conducted Diary Topic</th>
                            <th style="width: 55px;" class="text-center">CO</th>
                            <th style="width: 95px;" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recon['paired_rows'])): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    No lesson plan or diary records found for this course offering.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recon['paired_rows'] as $row): 
                                $p = $row['planned'];
                                $a = $row['actual'];
                            ?>
                                <tr>
                                    <!-- Planned Side -->
                                    <?php if ($p): ?>
                                        <td class="text-center fw-bold"><?= $p['lecture_number'] ?></td>
                                        <td class="text-center"><span class="badge bg-light text-dark border">U<?= $p['unit_number'] ?></span></td>
                                        <td><?= htmlspecialchars($p['planned_topic']) ?></td>
                                        <td class="text-center border-end"><span class="badge bg-primary">CO<?= $p['co_number'] ?></span></td>
                                    <?php else: ?>
                                        <td colspan="4" class="text-center text-muted bg-light border-end italic">
                                            <em>(Additional / Compensatory Period)</em>
                                        </td>
                                    <?php endif; ?>

                                    <!-- Actual Diary Side -->
                                    <?php if ($a): ?>
                                        <td class="text-center text-nowrap">
                                            <span class="fw-bold"><?= date('d-m-Y', strtotime($a['date'])) ?></span><br>
                                            <small class="text-muted"><?= htmlspecialchars($a['hour_desc'] ?? ('Hr ' . $a['hour'])) ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($a['diary']) ?></td>
                                        <td class="text-center">
                                            <?php if (!empty($a['co_number'])): ?>
                                                <span class="badge bg-success">CO<?= $a['co_number'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($row['is_compensatory']): ?>
                                                <span class="badge bg-warning text-dark">Compensatory</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Completed</span>
                                            <?php endif; ?>
                                        </td>
                                    <?php else: ?>
                                        <td colspan="4" class="text-center text-danger bg-light">
                                            <i class="bi bi-clock-history me-1"></i>Pending / Uncovered
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- End-of-Course Declaration & Sign-off Form -->
        <div class="card shadow-sm mb-4 border-primary">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="bi bi-file-earmark-check me-1"></i>Course Completion Sign-Off & Compliance Statement (NBA Criterion 2.2)
            </div>
            <div class="card-body">
                <form action="faclessonplanreconciliation.php" method="post">
                    <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">
                    <input type="hidden" name="save_audit" value="1">
                    <input type="hidden" name="total_planned_lectures" value="<?= $recon['total_planned'] ?>">
                    <input type="hidden" name="total_actual_conducted" value="<?= $recon['total_conducted'] ?>">
                    <input type="hidden" name="total_compensatory_classes" value="<?= $recon['compensatory_count'] ?>">
                    <input type="hidden" name="syllabus_completion_pct" value="<?= $recon['completion_pct'] ?>">

                    <h6 class="fw-bold text-primary mb-3">1. Unit-Wise Completion Dates:</h6>
                    <div class="row g-3 mb-4">
                        <?php for ($u = 1; $u <= 5; $u++): 
                            $val = $audit["unit{$u}_completion_date"] ?? ($recon['suggested_unit_dates'][$u] ?? '');
                        ?>
                            <div class="col-md">
                                <label class="form-label small fw-bold">Unit <?= $u ?> Completed On:</label>
                                <input type="date" name="unit<?= $u ?>_completion_date" class="form-control form-control-sm" value="<?= htmlspecialchars($val ?? '') ?>">
                            </div>
                        <?php endfor; ?>
                    </div>

                    <h6 class="fw-bold text-primary mb-3">2. Deviation & Compliance Disclosures:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Reasons for Schedule Deviations / Curriculum Lag (if any):</label>
                            <textarea name="deviations_reason" class="form-control form-control-sm" rows="3" placeholder="e.g. Topics in Unit 3 required additional clarification; classes delayed due to Mid Exams or University Youth Fest..."><?= htmlspecialchars($audit['deviations_reason'] ?? '') ?></textarea>
                            <small class="text-muted">Explains any divergence between scheduled and actual dates.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Compensatory / Remedial Measures Taken:</label>
                            <textarea name="compensatory_actions" class="form-control form-control-sm" rows="3" placeholder="e.g. Conducted 3 compensatory classes on alternate Saturdays; extra problem-solving tutorial conducted for Unit 4..."><?= htmlspecialchars($audit['compensatory_actions'] ?? '') ?></textarea>
                            <small class="text-muted">Remedial actions taken to ensure 100% syllabus completion.</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Content Beyond Syllabus / Value Added Topics (NBA Criterion 2.1):</label>
                            <textarea name="topics_beyond_syllabus" class="form-control form-control-sm" rows="2" placeholder="e.g. Demonstrated industry NoSQL database MongoDB and distributed query optimization..."><?= htmlspecialchars($audit['topics_beyond_syllabus'] ?? '') ?></textarea>
                            <small class="text-muted">Contemporary topics and real-world tools introduced beyond the prescribed syllabus.</small>
                        </div>
                    </div>

                    <div class="border rounded p-3 bg-light mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="certifyCheck" required <?= ($signoffStatus === 'SUBMITTED' || $signoffStatus === 'APPROVED') ? 'checked' : '' ?>>
                            <label class="form-check-label small fw-bold" for="certifyCheck">
                                I hereby certify that the syllabus has been completed in accordance with the academic regulations, and the Course Delivery Plan has been reconciled with the actual Teaching Diary entries.
                            </label>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2">
                        <button type="submit" name="is_final_submission" value="0" class="btn btn-outline-secondary">
                            <i class="bi bi-save me-1"></i>Save Draft
                        </button>
                        <button type="submit" name="is_final_submission" value="1" class="btn btn-success">
                            <i class="bi bi-send-check me-1"></i>Submit Course Completion to HOD
                        </button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once("facfooter.php"); ?>
