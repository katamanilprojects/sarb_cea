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
