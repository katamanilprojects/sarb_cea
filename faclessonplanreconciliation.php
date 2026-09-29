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

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selected_sub_id) {
    // 1. Save Diary Mappings if submitted
    if (isset($_POST['mapping']) && is_array($_POST['mapping'])) {
        $mapRes = $lpService->saveDiaryLessonPlanMappings($selected_sub_id, $_POST['mapping']);
        if (isset($_POST['save_mappings_only'])) {
            if ($mapRes['status'] == 1) {
                $msg = "Diary-to-Lesson-Plan mappings saved successfully (" . $mapRes['updated_count'] . " classes updated).";
            } else {
                $err = $mapRes['error'] ?? "Failed to save diary mappings.";
            }
        }
    }

    // 2. Save Course Completion Audit (Draft or Final)
    if (isset($_POST['save_audit'])) {
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
                    <h6 class="text-muted mb-1">Syllabus Coverage</h6>
                    <h3 class="text-info mb-0"><?= $recon['completion_pct'] ?>%</h3>
                    <small class="text-muted"><?= ($recon['completion_pct'] >= 100) ? 'Full Coverage' : 'Deficit Identified' ?></small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-warning text-center p-3 h-100">
                    <h6 class="text-muted mb-1">Compensatory / Extra</h6>
                    <h3 class="text-warning mb-0"><?= $recon['compensatory_count'] ?></h3>
                    <small class="text-muted">Revision / Beyond plan</small>
                </div>
            </div>
        </div>

        <!-- Uncovered Planned Lectures Alert -->
        <?php if (!empty($recon['planned_lectures'])): ?>
            <?php if (!empty($recon['uncovered_lectures'])): ?>
                <div class="alert alert-warning py-2 px-3 mb-4 small d-flex align-items-center">
                    <i class="bi bi-exclamation-triangle-fill fs-5 me-2 text-warning"></i>
                    <div>
                        <strong>Uncovered Planned Lectures (<?= count($recon['uncovered_lectures']) ?>):</strong>
                        <?php 
                        $uncovLabels = array_map(function($ul) {
                            return "Lec #" . $ul['lecture_number'] . " (" . htmlspecialchars(mb_substr($ul['planned_topic'], 0, 30)) . "... [CO" . $ul['co_number'] . "])";
                        }, $recon['uncovered_lectures']);
                        echo implode(', ', array_slice($uncovLabels, 0, 4));
                        if (count($uncovLabels) > 4) {
                            echo " and " . (count($uncovLabels) - 4) . " more";
                        }
                        ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-success py-2 px-3 mb-4 small d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-5 me-2 text-success"></i>
                    <div>
                        <strong>All <?= $recon['total_planned'] ?> planned lectures have been matched with conducted teaching diary classes!</strong>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Main Form encompassing both Step 1 (Mappings) and Step 2 (Audit Signoff) -->
        <form action="faclessonplanreconciliation.php" method="post" id="reconForm">
            <input type="hidden" name="sub_id" value="<?= $selected_sub_id ?>">

            <!-- STEP 1: Diary to Lesson Plan Reconciliation Table -->
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-light d-flex flex-wrap justify-content-between align-items-center py-2">
                    <div>
                        <span class="fw-bold text-dark"><i class="bi bi-diagram-3-fill text-primary me-2"></i>Step 1: End-of-Course Diary to Lesson Plan Reconciliation</span>
                        <span class="badge bg-secondary ms-2"><?= $recon['total_conducted'] ?> Conducted Classes</span>
                    </div>
                    <div class="d-flex gap-2 mt-2 mt-md-0">
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="autoSequenceMappings()" title="Map diary entries in sequential 1-to-1 order">
                            <i class="bi bi-lightning-charge-fill me-1"></i>1-Click Auto-Sequence
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearAllMappings()" title="Reset all mappings to unmapped">
                            <i class="bi bi-x-circle me-1"></i>Reset
                        </button>
                        <button type="submit" name="save_mappings_only" value="1" class="btn btn-sm btn-primary">
                            <i class="bi bi-save me-1"></i>Save Mappings
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 560px; overflow-y: auto;">
                        <table class="table table-sm table-bordered table-hover align-middle mb-0" style="font-size: 13px;">
                            <thead class="table-dark sticky-top">
                                <tr>
                                    <th style="width: 40px;" class="text-center">#</th>
                                    <th style="width: 110px;" class="text-center">Date & Hr</th>
                                    <th style="min-width: 250px;">Conducted Diary Entry (Actual What Was Taught)</th>
                                    <th style="min-width: 320px;">Map to Planned Lecture (Course Delivery Plan)</th>
                                    <th style="width: 65px;" class="text-center">CO</th>
                                    <th style="width: 100px;" class="text-center">Type</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recon['diary_entries'])): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">
                                            No attendance or diary records found for this course offering.
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $sno = 1;
                                    foreach ($recon['diary_entries'] as $d): 
                                        $dId = (int)$d['id'];
                                        $currLpId = $d['lesson_plan_id'];
                                        $isCompensatory = ($currLpId !== null && (int)$currLpId === 0);
                                        $isMapped = (!empty($currLpId) && (int)$currLpId > 0);
                                    ?>
                                        <tr id="diary-row-<?= $dId ?>">
                                            <td class="text-center fw-bold text-muted"><?= $sno++ ?></td>
                                            <td class="text-center text-nowrap">
                                                <span class="fw-bold"><?= date('d-m-Y', strtotime($d['date'])) ?></span><br>
                                                <small class="text-muted"><?= htmlspecialchars($d['hour_desc'] ?? ('Hr ' . $d['hour'])) ?></small>
                                            </td>
                                            <td>
                                                <div class="p-1 rounded bg-light border-start border-3 border-secondary" style="font-family: inherit;">
                                                    <?= nl2br(htmlspecialchars($d['diary'])) ?>
                                                </div>
                                            </td>
                                            <td>
                                                <select name="mapping[<?= $dId ?>]" class="form-select form-select-sm diary-mapping-select" onchange="updateRowCoBadge(this, <?= $dId ?>)">
                                                    <option value="" data-co="" data-type="unmapped">-- Unmapped / Select Lecture --</option>
                                                    <option value="0" data-co="" data-type="compensatory" <?= $isCompensatory ? 'selected' : '' ?>>
                                                        -- Extra / Compensatory / Revision Class --
                                                    </option>
                                                    <?php foreach ($recon['planned_lectures'] as $p): ?>
                                                        <option value="<?= $p['id'] ?>" 
                                                                data-co="<?= $p['co_number'] ?>" 
                                                                data-unit="<?= $p['unit_number'] ?>"
                                                                data-type="planned"
                                                                <?= ($currLpId == $p['id']) ? 'selected' : '' ?>>
                                                            Lec #<?= $p['lecture_number'] ?>: <?= htmlspecialchars(mb_substr($p['planned_topic'], 0, 52)) ?> (U<?= $p['unit_number'] ?>, CO<?= $p['co_number'] ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td class="text-center" id="co-cell-<?= $dId ?>">
                                                <?php if (!empty($d['co_number'])): ?>
                                                    <span class="badge bg-success">CO<?= $d['co_number'] ?></span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center" id="type-cell-<?= $dId ?>">
                                                <?php if ($isCompensatory): ?>
                                                    <span class="badge bg-warning text-dark">Compensatory</span>
                                                <?php elseif ($isMapped): ?>
                                                    <span class="badge bg-primary">Planned</span>
                                                <?php else: ?>
                                                    <span class="badge bg-light text-muted border">Unmapped</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center py-2">
                    <small class="text-muted"><i class="bi bi-info-circle me-1"></i>Mapping links each conducted class to the planned topic and automatically updates the target Course Outcome (CO).</small>
                    <button type="submit" name="save_mappings_only" value="1" class="btn btn-sm btn-primary">
                        <i class="bi bi-save me-1"></i>Save Mappings
                    </button>
                </div>
            </div>

            <!-- STEP 2: Course Delivery Compliance Sign-Off & Audit Form -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="bi bi-file-earmark-check me-2"></i>Step 2: Course Completion Sign-Off & Compliance Statement (NBA Criterion 2.2)
                </div>
                <div class="card-body">
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

                    <h6 class="fw-bold text-primary mb-3">2. Schedule Deviations & Remedial Disclosures:</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Reasons for Schedule Deviations / Pacing Remarks (if any):</label>
                            <textarea name="deviations_reason" class="form-control form-control-sm" rows="3" placeholder="e.g. Unit 3 required extra mathematical derivations; timetable rescheduled due to Mid Exams..."><?= htmlspecialchars($audit['deviations_reason'] ?? '') ?></textarea>
                            <small class="text-muted">Disclose reasons if actual timeline differed from planned schedule.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Compensatory / Remedial Measures Taken:</label>
                            <textarea name="compensatory_actions" class="form-control form-control-sm" rows="3" placeholder="e.g. Conducted compensatory classes on alternate Saturdays; extra revision tutorial conducted for Unit 4..."><?= htmlspecialchars($audit['compensatory_actions'] ?? '') ?></textarea>
                            <small class="text-muted">Actions taken to ensure 100% curriculum compliance.</small>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label small fw-bold">Content Beyond Syllabus / Value Added Topics (NBA Criterion 2.1):</label>
                            <textarea name="topics_beyond_syllabus" class="form-control form-control-sm" rows="2" placeholder="e.g. Introduced industry case studies, demonstration of practical open-source tools..."><?= htmlspecialchars($audit['topics_beyond_syllabus'] ?? '') ?></textarea>
                            <small class="text-muted">Contemporary topics and real-world tools introduced beyond prescribed syllabus.</small>
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
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
const plannedLecturesList = <?= json_encode(array_values(array_map(function($p) {
    return [
        'id' => (int)$p['id'],
        'lecture_number' => (int)$p['lecture_number'],
        'unit_number' => (int)$p['unit_number'],
        'co_number' => (int)$p['co_number']
    ];
}, $recon['planned_lectures'] ?? []))) ?>;

function autoSequenceMappings() {
    const selects = document.querySelectorAll('.diary-mapping-select');
    if (!selects.length || !plannedLecturesList.length) return;

    selects.forEach((sel, idx) => {
        if (idx < plannedLecturesList.length) {
            sel.value = plannedLecturesList[idx].id;
        } else {
            sel.value = "0"; // Compensatory for classes beyond planned count
        }
        sel.dispatchEvent(new Event('change'));
    });
}

function clearAllMappings() {
    const selects = document.querySelectorAll('.diary-mapping-select');
    selects.forEach(sel => {
        sel.value = "";
        sel.dispatchEvent(new Event('change'));
    });
}

function updateRowCoBadge(selectElem, diaryId) {
    const selectedOpt = selectElem.options[selectElem.selectedIndex];
    const coCell = document.getElementById('co-cell-' + diaryId);
    const typeCell = document.getElementById('type-cell-' + diaryId);

    if (!selectedOpt) return;

    const coNum = selectedOpt.getAttribute('data-co');
    const optType = selectedOpt.getAttribute('data-type');

    if (coCell) {
        if (coNum && coNum.trim() !== '') {
            coCell.innerHTML = '<span class="badge bg-success">CO' + coNum + '</span>';
        } else {
            coCell.innerHTML = '<span class="badge bg-secondary">-</span>';
        }
    }

    if (typeCell) {
        if (optType === 'compensatory') {
            typeCell.innerHTML = '<span class="badge bg-warning text-dark">Compensatory</span>';
        } else if (optType === 'planned') {
            typeCell.innerHTML = '<span class="badge bg-primary">Planned</span>';
        } else {
            typeCell.innerHTML = '<span class="badge bg-light text-muted border">Unmapped</span>';
        }
    }
}
</script>

<?php require_once("facfooter.php"); ?>
