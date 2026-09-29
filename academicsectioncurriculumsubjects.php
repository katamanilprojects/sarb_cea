<?php
session_start();
$page_title = "Curriculum Subjects";
require_once("academicsectionheader.php");
require_once("curriculum_subject.class.php");

$obj = new CurriculumSubject();
$msg = '';
$errmsg = '';

// Check if editing a specific subject
$editSubject = null;
if (!empty($_GET['edit_id'])) {
    $editRes = $obj->getSubjectById((int)$_GET['edit_id']);
    if (!empty($editRes['status']) && !empty($editRes['data'])) {
        $editSubject = $editRes['data'];
        // Auto-hydrate filter selections if not provided in URL
        if (empty($_REQUEST['prog_id'])) $_REQUEST['prog_id'] = $editSubject['prog_id'];
        if (empty($_REQUEST['reg_id'])) $_REQUEST['reg_id'] = $editSubject['reg_id'];
        if (empty($_REQUEST['spec_id'])) $_REQUEST['spec_id'] = $editSubject['spec_id'];
        if (empty($_REQUEST['yearsem'])) $_REQUEST['yearsem'] = $editSubject['yearsem'];
    }
}

// Handle Actions (Add, Update, Inactivate / Toggle Status)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_subject') {
        $res = $obj->addOrUpdateSubject($_POST);
        if (!empty($res['status'])) {
            $msg = $res['message'];
            // Reset edit mode after successful save
            $editSubject = null;
        } else {
            $errmsg = $res['error'];
        }
    } elseif ($action === 'toggle_status') {
        $subId = (int)($_POST['id'] ?? 0);
        $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : null;
        $res = $obj->toggleStatusSubject($subId, $newStatus);
        if (!empty($res['status'])) {
            $msg = $res['message'];
        } else {
            $errmsg = $res['error'];
        }
    }
}

// Filter selections
$selectedProgId = (int)($_REQUEST['prog_id'] ?? 0);
$selectedRegId = (int)($_REQUEST['reg_id'] ?? 0);
$selectedSpecId = (int)($_REQUEST['spec_id'] ?? 0);
$selectedYearSem = trim($_REQUEST['yearsem'] ?? '');

$programs = $obj->getAllPrograms()['data'] ?? [];

// Filter regulations by selected program if chosen, otherwise show all with program label
$regulations = [];
if (!empty($selectedProgId)) {
    $regulations = $obj->getRegulationsByProgramId($selectedProgId)['data'] ?? [];
} else {
    $regulations = $obj->getAllRegulations()['data'] ?? [];
}

$specializations = [];
if (!empty($selectedProgId)) {
    $specializations = $obj->getSpecializationsByProgramId($selectedProgId)['data'] ?? [];
}
$yearSemOptions = $obj->getYearSemOptions();

// Load subjects if all 4 context keys are provided
$subjectsList = [];
if (!empty($selectedProgId) && !empty($selectedRegId) && !empty($selectedSpecId) && !empty($selectedYearSem)) {
    $subjectsList = $obj->getSubjectsByContext($selectedProgId, $selectedRegId, $selectedSpecId, $selectedYearSem)['data'] ?? [];
}
?>

<div class="container my-3">
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-book me-2"></i>Curriculum Subjects Management (Academic Section)</h5>
            <small class="badge bg-light text-primary">Master Subject Catalog</small>
        </div>
        <div class="card-body">
            <?php if (!empty($msg)) { ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($msg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <?php if (!empty($errmsg)) { ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($errmsg); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php } ?>

            <!-- Context Filter Form -->
            <form method="GET" action="academicsectioncurriculumsubjects.php" class="row g-2 mb-2" id="filterForm">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Program <span class="text-danger">*</span></label>
                    <select name="prog_id" id="prog_id" class="form-select form-select-sm" required onchange="onProgramChange()">
                        <option value="">-- Select Program --</option>
                        <?php foreach ($programs as $prog): ?>
                            <option value="<?= $prog['id'] ?>" <?= ($selectedProgId == $prog['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prog['prog_shortname'] . ' - ' . $prog['prog_fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Regulation <span class="text-danger">*</span></label>
                    <select name="reg_id" id="reg_id" class="form-select form-select-sm" required>
                        <option value="">-- Select Regulation --</option>
                        <?php foreach ($regulations as $reg): ?>
                            <option value="<?= $reg['id'] ?>" <?= ($selectedRegId == $reg['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($reg['regulation'] . ' (' . ($reg['prog_shortname'] ?? '') . ')') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Specialization <span class="text-danger">*</span></label>
                    <select name="spec_id" id="spec_id" class="form-select form-select-sm" required>
                        <option value="">-- Select Specialization --</option>
                        <?php foreach ($specializations as $spec): ?>
                            <option value="<?= $spec['id'] ?>" <?= ($selectedSpecId == $spec['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($spec['spec_shortname'] . ' - ' . $spec['spec_fullname']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold">Year - Sem <span class="text-danger">*</span></label>
                    <select name="yearsem" id="yearsem" class="form-select form-select-sm" required>
                        <option value="">-- Select Year & Sem --</option>
                        <?php foreach ($yearSemOptions as $yso): ?>
                            <option value="<?= $yso ?>" <?= ($selectedYearSem == $yso) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($yso) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 text-end mt-3">
                    <button type="submit" class="btn btn-primary btn-sm px-4">
                        <i class="bi bi-search me-1"></i> Get Subjects
                    </button>
                    <?php if (!empty($selectedProgId)): ?>
                        <a href="academicsectioncurriculumsubjects.php" class="btn btn-outline-secondary btn-sm ms-2">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php if (!empty($selectedProgId) && !empty($selectedRegId) && !empty($selectedSpecId) && !empty($selectedYearSem)): ?>
        <!-- Add / Edit Form Card -->
        <div class="card shadow-sm mb-4 border-info">
            <div class="card-header bg-info bg-opacity-10 text-dark d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-<?= !empty($editSubject) ? 'pencil-square text-warning' : 'plus-circle text-success' ?> me-2"></i>
                    <?= !empty($editSubject) ? 'Edit Curriculum Subject: ' . htmlspecialchars($editSubject['subcode']) : 'Add New Curriculum Subject' ?>
                </h6>
                <?php if (!empty($editSubject)): ?>
                    <a href="academicsectioncurriculumsubjects.php?prog_id=<?= $selectedProgId ?>&reg_id=<?= $selectedRegId ?>&spec_id=<?= $selectedSpecId ?>&yearsem=<?= urlencode($selectedYearSem) ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-x-circle me-1"></i> Cancel Edit
                    </a>
                <?php endif; ?>
            </div>
            <div class="card-body">
                <form method="POST" action="academicsectioncurriculumsubjects.php" id="subjectForm">
                    <input type="hidden" name="action" value="save_subject">
                    <input type="hidden" name="id" value="<?= !empty($editSubject) ? (int)$editSubject['id'] : '' ?>">
                    <input type="hidden" name="prog_id" value="<?= $selectedProgId ?>">
                    <input type="hidden" name="reg_id" value="<?= $selectedRegId ?>" id="form_reg_id">
                    <input type="hidden" name="spec_id" value="<?= $selectedSpecId ?>">
                    <input type="hidden" name="yearsem" value="<?= htmlspecialchars($selectedYearSem) ?>">

                    <div class="row g-2">
                        <!-- S.No -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">S.No <span class="text-danger">*</span></label>
                            <select name="subject_sno" id="subject_sno" class="form-select form-select-sm" required>
                                <?php 
                                $nextSno = !empty($editSubject) ? $editSubject['subject_sno'] : (count($subjectsList) + 1);
                                for ($i = 1; $i <= 20; $i++): ?>
                                    <option value="<?= $i ?>" <?= ($nextSno == $i) ? 'selected' : '' ?>><?= $i ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>

                        <!-- Subject Code with Auto-Retrieval -->
                        <div class="col-md-3 position-relative">
                            <label class="form-label fw-bold">Subject Code <span class="text-danger">*</span></label>
                            <div class="input-group input-group-sm">
                                <input type="text" name="subcode" id="subcode" class="form-control text-uppercase" 
                                       placeholder="e.g. 23A05301T" required 
                                       value="<?= htmlspecialchars($editSubject['subcode'] ?? '') ?>"
                                       <?= empty($editSubject) ? 'onblur="lookupSubjectCode()"' : '' ?> autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="lookupSubjectCode()" title="Lookup catalog details">
                                    <i class="bi bi-search" id="lookupIcon"></i>
                                </button>
                            </div>
                            <small id="lookupStatus" class="form-text"></small>
                        </div>

                        <!-- Subject Short Name -->
                        <div class="col-md-2">
                            <label class="form-label fw-bold">Short Name <span class="text-danger">*</span></label>
                            <input type="text" name="sub_shortname" id="sub_shortname" class="form-control form-control-sm text-uppercase" 
                                   placeholder="e.g. DBMS" required value="<?= htmlspecialchars($editSubject['sub_shortname'] ?? '') ?>">
                        </div>

                        <!-- Subject Type -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Subject Type <span class="text-danger">*</span></label>
                            <select name="sub_type" id="sub_type" class="form-select form-select-sm" required>
                                <?php
                                $subTypes = ["Theory", "Lab", "Mandatory Course", "Skill Oriented Course", "Honors / Minors", "Project", "Comprehensive Viva", "Audit Course", "Seminar"];
                                $currentType = $editSubject['sub_type'] ?? 'Theory';
                                foreach ($subTypes as $st): ?>
                                    <option value="<?= $st ?>" <?= ($currentType === $st) ? 'selected' : '' ?>><?= $st ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Full Name -->
                        <div class="col-md-12">
                            <label class="form-label fw-bold">Subject Full Name <span class="text-danger">*</span></label>
                            <input type="text" name="sub_fullname" id="sub_fullname" class="form-control form-control-sm" 
                                   placeholder="e.g. Database Management Systems" required 
                                   value="<?= htmlspecialchars($editSubject['sub_fullname'] ?? '') ?>">
                        </div>

                        <!-- L - T - P - C (Hours and Credits) -->
                        <div class="col-md-3">
                            <label class="form-label fw-bold">Lecture Hours (L)</label>
                            <input type="number" step="0.5" min="0" max="20" name="lecture_hours" id="lecture_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['lecture_hours'] ?? '3.0') ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Tutorial Hours (T)</label>
                            <input type="number" step="0.5" min="0" max="10" name="tutorial_hours" id="tutorial_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['tutorial_hours'] ?? '0.0') ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Practical Hours (P)</label>
                            <input type="number" step="0.5" min="0" max="20" name="practical_hours" id="practical_hours" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['practical_hours'] ?? '0.0') ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold">Credits (C)</label>
                            <input type="number" step="0.5" min="0" max="30" name="credits" id="credits" 
                                   class="form-control form-control-sm" value="<?= htmlspecialchars($editSubject['credits'] ?? '3.0') ?>" required>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button type="submit" class="btn btn-<?= !empty($editSubject) ? 'warning' : 'success' ?> btn-sm px-4">
                            <i class="bi bi-<?= !empty($editSubject) ? 'check-circle' : 'plus-circle' ?> me-1"></i>
                            <?= !empty($editSubject) ? 'Update Subject' : 'Save Subject' ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Tabular List Card -->
        <div class="card shadow-sm">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="bi bi-list-columns-reverse me-2"></i>Defined Subjects (<?= count($subjectsList) ?>)
                </h6>
                <span class="badge bg-secondary">
                    <?= htmlspecialchars($selectedYearSem) ?>
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 text-center">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;">S.No</th>
                                <th style="width: 130px;">Code</th>
                                <th class="text-start">Subject Full Name</th>
                                <th>Short Name</th>
                                <th>Type</th>
                                <th style="width: 50px;">L</th>
                                <th style="width: 50px;">T</th>
                                <th style="width: 50px;">P</th>
                                <th style="width: 60px;">Credits</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 150px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($subjectsList)): ?>
                                <tr>
                                    <td colspan="11" class="text-muted py-4">No subjects found for this regulation, branch, and semester. Use the form above to add subjects.</td>
                                </tr>
                            <?php else: ?>
                                <?php 
                                $totalCredits = 0;
                                foreach ($subjectsList as $sub): 
                                    $isActive = ((int)($sub['status'] ?? 1) === 1);
                                    if ($isActive) {
                                        $totalCredits += (float)$sub['credits'];
                                    }
                                    $rowClass = $isActive ? '' : 'table-light text-muted opacity-75';
                                ?>
                                    <tr class="<?= $rowClass ?>">
                                        <td class="fw-bold"><?= htmlspecialchars($sub['subject_sno']) ?></td>
                                        <td>
                                            <span class="badge <?= $isActive ? 'bg-primary' : 'bg-secondary' ?> fs-7">
                                                <?= htmlspecialchars($sub['subcode']) ?>
                                            </span>
                                        </td>
                                        <td class="text-start fw-semibold">
                                            <?= htmlspecialchars($sub['sub_fullname']) ?>
                                            <?php if (!$isActive): ?>
                                                <span class="badge bg-danger ms-1 text-white">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($sub['sub_shortname']) ?></td>
                                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($sub['sub_type']) ?></span></td>
                                        <td><?= htmlspecialchars($sub['lecture_hours']) ?></td>
                                        <td><?= htmlspecialchars($sub['tutorial_hours']) ?></td>
                                        <td><?= htmlspecialchars($sub['practical_hours']) ?></td>
                                        <td class="fw-bold <?= $isActive ? 'text-success' : 'text-muted' ?>"><?= htmlspecialchars($sub['credits']) ?></td>
                                        <td>
                                            <?php if ($isActive): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <!-- Edit button -->
                                            <a href="academicsectioncurriculumsubjects.php?prog_id=<?= $selectedProgId ?>&reg_id=<?= $selectedRegId ?>&spec_id=<?= $selectedSpecId ?>&yearsem=<?= urlencode($selectedYearSem) ?>&edit_id=<?= $sub['id'] ?>" class="btn btn-sm btn-outline-warning me-1" title="Edit Subject">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>

                                            <!-- BOS Master Course Outcomes button -->
                                            <button type="button" class="btn btn-sm btn-outline-primary me-1" onclick="openMasterCOModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['subcode'])) ?>', '<?= htmlspecialchars(addslashes($sub['sub_fullname'])) ?>')" title="Manage BOS Master Course Outcomes">
                                                <i class="bi bi-award"></i> BOS COs
                                            </button>

                                            <!-- Inactivate / Activate toggle form -->
                                            <form method="POST" action="academicsectioncurriculumsubjects.php" class="d-inline" onsubmit="return confirm('<?= $isActive ? "Are you sure you want to inactivate this subject?" : "Are you sure you want to activate this subject?" ?>');">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="id" value="<?= $sub['id'] ?>">
                                                <input type="hidden" name="new_status" value="<?= $isActive ? 0 : 1 ?>">
                                                <input type="hidden" name="prog_id" value="<?= $selectedProgId ?>">
                                                <input type="hidden" name="reg_id" value="<?= $selectedRegId ?>">
                                                <input type="hidden" name="spec_id" value="<?= $selectedSpecId ?>">
                                                <input type="hidden" name="yearsem" value="<?= htmlspecialchars($selectedYearSem) ?>">
                                                <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-danger' : 'btn-outline-success' ?>" title="<?= $isActive ? 'Inactivate Subject' : 'Activate Subject' ?>">
                                                    <i class="bi bi-<?= $isActive ? 'slash-circle' : 'check-circle' ?>"></i> <?= $isActive ? 'Inactivate' : 'Activate' ?>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr class="table-secondary fw-bold">
                                    <td colspan="8" class="text-end pe-3">Active Total Credits:</td>
                                    <td class="text-success"><?= number_format($totalCredits, 1) ?></td>
                                    <td colspan="2"></td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Master BOS Course Outcomes Modal -->
<div class="modal fade" id="masterCOModal" tabindex="-1" aria-labelledby="masterCOModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="masterCOModalLabel"><i class="bi bi-award me-2"></i>Board of Studies (BOS) Master Course Outcomes</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-secondary py-2 mb-3">
                    <strong id="modalSubjectInfo">Subject Code & Name</strong>
                    <div class="text-muted small">These master catalog outcomes are inherited by default across faculty offerings under this syllabus.</div>
                </div>

                <div id="modalCOAlert"></div>

                <!-- Existing Master COs Table -->
                <h6 class="fw-bold mb-2"><i class="bi bi-list-check me-1"></i>Approved Catalog COs</h6>
                <div class="table-responsive mb-4">
                    <table class="table table-bordered table-sm align-middle text-center mb-0" id="masterCOTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 70px;">CO No.</th>
                                <th class="text-start">Course Outcome Description</th>
                                <th style="width: 140px;">Bloom's Level</th>
                                <th style="width: 100px;">Target %</th>
                                <th style="width: 90px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="masterCOTableBody">
                            <tr>
                                <td colspan="5" class="text-muted py-3">Loading master outcomes...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add / Edit Master CO Form -->
                <div class="card border-primary">
                    <div class="card-header bg-light fw-bold py-2">
                        <span id="formCardTitle"><i class="bi bi-plus-circle me-1"></i>Add / Update Master CO</span>
                    </div>
                    <div class="card-body py-2">
                        <form id="masterCOForm" onsubmit="submitMasterCO(event)">
                            <input type="hidden" id="modal_curr_sub_id" name="curr_sub_id" value="">
                            <input type="hidden" name="action" value="save_master_co">
                            <div class="row g-2 mb-2">
                                <div class="col-md-3">
                                    <label class="form-label small fw-bold">CO Number <span class="text-danger">*</span></label>
                                    <input type="number" id="modal_co_number" name="co_number" class="form-control form-control-sm" min="1" max="10" placeholder="e.g. 1" required>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-bold">Bloom's Taxonomy Level</label>
                                    <select id="modal_bloom_level" name="bloom_level" class="form-select form-select-sm">
                                        <option value="L1-Remember">L1 - Remember</option>
                                        <option value="L2-Understand">L2 - Understand</option>
                                        <option value="L3-Apply" selected>L3 - Apply</option>
                                        <option value="L4-Analyze">L4 - Analyze</option>
                                        <option value="L5-Evaluate">L5 - Evaluate</option>
                                        <option value="L6-Create">L6 - Create</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-bold">Target Threshold (%)</label>
                                    <div class="input-group input-group-sm">
                                        <input type="number" step="0.1" id="modal_target_threshold" name="target_threshold_percent" class="form-control" value="60.0" min="1" max="100" required>
                                        <span class="input-group-text">%</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-bold">CO Statement / Description <span class="text-danger">*</span></label>
                                    <textarea id="modal_co_description" name="co_description" class="form-control form-control-sm" rows="2" placeholder="Upon completion of this course, students will be able to..." required></textarea>
                                </div>
                            </div>
                            <div class="text-end">
                                <button type="button" class="btn btn-outline-secondary btn-sm me-1" onclick="resetMasterCOForm()">Clear</button>
                                <button type="submit" class="btn btn-primary btn-sm" id="modalSubmitBtn"><i class="bi bi-save me-1"></i>Save Master CO</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function onProgramChange() {
    const progId = document.getElementById('prog_id').value;
    const specSelect = document.getElementById('spec_id');
    const regSelect = document.getElementById('reg_id');

    specSelect.innerHTML = '<option value="">Loading...</option>';
    regSelect.innerHTML = '<option value="">Loading...</option>';

    if (!progId) {
        specSelect.innerHTML = '<option value="">-- Select Specialization --</option>';
        regSelect.innerHTML = '<option value="">-- Select Regulation --</option>';
        return;
    }

    // Fetch specializations for program
    fetch('curriculum_subject_ajax.php?action=get_specializations&prog_id=' + progId)
        .then(response => response.json())
        .then(res => {
            specSelect.innerHTML = '<option value="">-- Select Specialization --</option>';
            if (res.status === 1 && res.data) {
                res.data.forEach(spec => {
                    const opt = document.createElement('option');
                    opt.value = spec.id;
                    opt.textContent = spec.spec_shortname + ' - ' + spec.spec_fullname;
                    specSelect.appendChild(opt);
                });
            }
        })
        .catch(err => {
            console.error('Error fetching specializations:', err);
            specSelect.innerHTML = '<option value="">-- Select Specialization --</option>';
        });

    // Fetch regulations for program
    fetch('curriculum_subject_ajax.php?action=get_regulations&prog_id=' + progId)
        .then(response => response.json())
        .then(res => {
            regSelect.innerHTML = '<option value="">-- Select Regulation --</option>';
            if (res.status === 1 && res.data) {
                res.data.forEach(reg => {
                    const opt = document.createElement('option');
                    opt.value = reg.id;
                    opt.textContent = reg.regulation + ' (' + (reg.prog_shortname || '') + ')';
                    regSelect.appendChild(opt);
                });
            }
        })
        .catch(err => {
            console.error('Error fetching regulations:', err);
            regSelect.innerHTML = '<option value="">-- Select Regulation --</option>';
        });
}

function lookupSubjectCode() {
    const subcodeInput = document.getElementById('subcode');
    const subcode = subcodeInput ? subcodeInput.value.trim() : '';
    const regId = document.getElementById('form_reg_id') ? document.getElementById('form_reg_id').value : '';
    const statusEl = document.getElementById('lookupStatus');
    const iconEl = document.getElementById('lookupIcon');

    if (!subcode) return;

    if (iconEl) iconEl.className = 'spinner-border spinner-border-sm';
    if (statusEl) {
        statusEl.className = 'form-text text-muted';
        statusEl.textContent = 'Searching catalog...';
    }

    fetch('curriculum_subject_ajax.php?action=lookup_code&subcode=' + encodeURIComponent(subcode) + '&reg_id=' + encodeURIComponent(regId))
        .then(r => r.json())
        .then(res => {
            if (iconEl) iconEl.className = 'bi bi-search';
            if (res.status === 1 && res.data) {
                const d = res.data;
                if (statusEl) {
                    statusEl.className = 'form-text text-success fw-bold';
                    statusEl.textContent = '✓ Details auto-retrieved from catalog!';
                }
                if (d.sub_fullname) document.getElementById('sub_fullname').value = d.sub_fullname;
                if (d.sub_shortname) document.getElementById('sub_shortname').value = d.sub_shortname;
                if (d.sub_type) document.getElementById('sub_type').value = d.sub_type;
                if (d.lecture_hours !== undefined) document.getElementById('lecture_hours').value = d.lecture_hours;
                if (d.tutorial_hours !== undefined) document.getElementById('tutorial_hours').value = d.tutorial_hours;
                if (d.practical_hours !== undefined) document.getElementById('practical_hours').value = d.practical_hours;
                if (d.credits !== undefined) document.getElementById('credits').value = d.credits;
                if (d.subject_sno !== undefined && document.getElementById('subject_sno')) {
                    document.getElementById('subject_sno').value = d.subject_sno;
                }
            } else {
                if (statusEl) {
                    statusEl.className = 'form-text text-muted';
                    statusEl.textContent = 'New subject code (enter details below)';
                }
            }
        })
        .catch(err => {
            if (iconEl) iconEl.className = 'bi bi-search';
            if (statusEl) {
                statusEl.className = 'form-text text-danger';
                statusEl.textContent = 'Lookup error';
            }
        });
}

// Master BOS Course Outcomes Modal & AJAX Management
let currentMasterCOModalInstance = null;
let activeCurrSubId = null;

function openMasterCOModal(currSubId, subCode, subName) {
    activeCurrSubId = currSubId;
    document.getElementById('modal_curr_sub_id').value = currSubId;
    document.getElementById('modalSubjectInfo').textContent = subCode + ' - ' + subName;
    document.getElementById('modalCOAlert').innerHTML = '';
    resetMasterCOForm();

    loadMasterCOs(currSubId);

    const modalEl = document.getElementById('masterCOModal');
    if (!currentMasterCOModalInstance) {
        currentMasterCOModalInstance = new bootstrap.Modal(modalEl);
    }
    currentMasterCOModalInstance.show();
}

function loadMasterCOs(currSubId) {
    const tbody = document.getElementById('masterCOTableBody');
    tbody.innerHTML = '<tr><td colspan="5" class="text-muted py-3"><div class="spinner-border spinner-border-sm me-2"></div>Loading master outcomes...</td></tr>';

    fetch('curriculum_subject_ajax.php?action=get_master_cos&curr_sub_id=' + currSubId)
        .then(r => r.json())
        .then(res => {
            if (res.status === 1 && res.data && res.data.length > 0) {
                let html = '';
                res.data.forEach(co => {
                    const descSafe = (co.co_description || '').replace(/"/g, '&quot;').replace(/'/g, "\\'");
                    const bloomSafe = co.bloom_level || 'L3-Apply';
                    const thresh = co.target_threshold_percent || '60.0';
                    html += `<tr>
                        <td><span class="badge bg-secondary">CO${co.co_number}</span></td>
                        <td class="text-start">${co.co_description || ''}</td>
                        <td><span class="badge bg-info text-dark">${bloomSafe}</span></td>
                        <td><span class="badge bg-light text-dark border">${thresh}%</span></td>
                        <td>
                            <button type="button" class="btn btn-outline-warning btn-sm py-0 px-1 me-1" onclick="editMasterCO(${co.co_number}, '${descSafe}', '${bloomSafe}', ${thresh})" title="Edit CO">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button type="button" class="btn btn-outline-danger btn-sm py-0 px-1" onclick="deleteMasterCO(${co.id}, ${co.co_number})" title="Delete CO">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    </tr>`;
                });
                tbody.innerHTML = html;
                const nextNum = res.data.length + 1;
                document.getElementById('modal_co_number').value = nextNum;
            } else {
                tbody.innerHTML = '<tr><td colspan="5" class="text-muted py-3">No master course outcomes defined yet. Use the form below to add CO1, CO2, etc.</td></tr>';
                document.getElementById('modal_co_number').value = 1;
            }
        })
        .catch(err => {
            console.error('Error loading master COs:', err);
            tbody.innerHTML = '<tr><td colspan="5" class="text-danger py-3">Failed to load outcomes. Please check console.</td></tr>';
        });
}

function submitMasterCO(e) {
    e.preventDefault();
    const btn = document.getElementById('modalSubmitBtn');
    const alertBox = document.getElementById('modalCOAlert');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const formData = new FormData(document.getElementById('masterCOForm'));

    fetch('curriculum_subject_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Master CO';

        if (res.status === 1) {
            alertBox.innerHTML = '<div class="alert alert-success alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-check-circle me-1"></i>' + (res.message || 'Master CO saved successfully.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
            resetMasterCOForm();
            loadMasterCOs(activeCurrSubId);
        } else {
            alertBox.innerHTML = '<div class="alert alert-danger alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>' + (res.error || 'Failed to save master CO.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-save me-1"></i>Save Master CO';
        alertBox.innerHTML = '<div class="alert alert-danger py-2 mb-2">Network error while saving.</div>';
    });
}

function editMasterCO(coNum, coDesc, bloomLevel, thresh) {
    document.getElementById('modal_co_number').value = coNum;
    document.getElementById('modal_co_description').value = coDesc;
    if (bloomLevel) document.getElementById('modal_bloom_level').value = bloomLevel;
    if (thresh) document.getElementById('modal_target_threshold').value = thresh;
    document.getElementById('formCardTitle').innerHTML = '<i class="bi bi-pencil-square me-1 text-warning"></i>Editing Master CO' + coNum;
    document.getElementById('modal_co_description').focus();
}

function deleteMasterCO(coId, coNum) {
    if (!confirm('Are you sure you want to delete Master CO' + coNum + '?')) return;

    const alertBox = document.getElementById('modalCOAlert');
    const formData = new FormData();
    formData.append('action', 'delete_master_co');
    formData.append('curr_sub_id', activeCurrSubId);
    formData.append('co_id', coId);

    fetch('curriculum_subject_ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(r => r.json())
    .then(res => {
        if (res.status === 1) {
            alertBox.innerHTML = '<div class="alert alert-success alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-check-circle me-1"></i>Master CO' + coNum + ' deleted.' +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
            loadMasterCOs(activeCurrSubId);
        } else {
            alertBox.innerHTML = '<div class="alert alert-danger alert-dismissible fade show py-2 mb-2">' +
                '<i class="bi bi-exclamation-triangle me-1"></i>' + (res.error || 'Failed to delete CO.') +
                '<button type="button" class="btn-close py-2" data-bs-dismiss="alert"></button></div>';
        }
    })
    .catch(err => {
        console.error('Error deleting master CO:', err);
        alertBox.innerHTML = '<div class="alert alert-danger py-2 mb-2">Network error while deleting CO.</div>';
    });
}

function resetMasterCOForm() {
    document.getElementById('modal_co_description').value = '';
    document.getElementById('modal_bloom_level').value = 'L3-Apply';
    document.getElementById('modal_target_threshold').value = '60.0';
    document.getElementById('formCardTitle').innerHTML = '<i class="bi bi-plus-circle me-1"></i>Add / Update Master CO';
    // Auto-calculate next CO number from current visible table rows
    const tbody = document.getElementById('masterCOTableBody');
    const rows = tbody ? tbody.querySelectorAll('tr[class!="text-muted"]') : [];
    let maxCoNum = 0;
    tbody && tbody.querySelectorAll('td:first-child .badge').forEach(badge => {
        const num = parseInt((badge.textContent || '').replace('CO', ''), 10);
        if (!isNaN(num) && num > maxCoNum) maxCoNum = num;
    });
    document.getElementById('modal_co_number').value = maxCoNum + 1;
}

// Attach live input debounce for instant auto-population
let lookupTimerAcademic = null;
document.addEventListener('DOMContentLoaded', function() {
    const subCodeEl = document.getElementById('subcode');
    if (subCodeEl) {
        subCodeEl.addEventListener('input', function() {
            clearTimeout(lookupTimerAcademic);
            const val = this.value.trim();
            if (val.length >= 3) {
                lookupTimerAcademic = setTimeout(lookupSubjectCode, 600);
            }
        });
    }
});
</script>
