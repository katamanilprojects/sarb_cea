<?php
/**
 * Controller: Superadmin Batch OBE Governance & Master Articulation Hierarchy
 * 
 * Master Standards (Stored Once): Vision -> Mission -> PEOs -> POs/PSOs -> Articulation
 * Cohort Consumption: Batches inherit master standards by Department & Admission Year.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
if ($role !== 'superadmin') {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_BATCH_GOVERNANCE');

$page_title = "OBE Governance & Cohort Inheritance";

require_once __DIR__ . '/header.php';
require_once __DIR__ . '/superadminmenu.php';
require_once __DIR__ . '/services/BatchOBEService.php';
require_once __DIR__ . '/departments.class.php';

$batchService = \Services\BatchOBEService::getInstance();
$deptsObj = new \Departments();

$allBatches = $batchService->getBatches();
$allDepts = $deptsObj->getAllDepartments()['data'] ?? [];

$selectedDeptId = isset($_GET['dept_id']) ? (int)$_GET['dept_id'] : ($allDepts[0]['id'] ?? 1);
$selectedBatchId = isset($_GET['batch_id']) ? (int)$_GET['batch_id'] : 0; // 0 = Department Master Mode
$activeTab = $_GET['tab'] ?? 'vision_mission';

$currentBatch = ($selectedBatchId > 0) ? $batchService->getBatchById($selectedBatchId) : null;

$succMsg = '';
$errMsg = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    \FeatureManager::requireWriteAccess('MOD_BATCH_GOVERNANCE');
    $token = $_POST['secretcode'] ?? '';
    if (empty($token) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $token)) {
        $errMsg = "Security validation failed (CSRF mismatch).";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 1);

        if ($action === 'save_vision_mission') {
            $vision = $_POST['vision_statement'] ?? '';
            $missions = $_POST['missions'] ?? [];
            $effectiveYear = (int)($_POST['effective_from_year'] ?? 2020);
            $res = $batchService->saveMasterVisionMission($selectedDeptId, $vision, $missions, $userId, $effectiveYear);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
            $activeTab = 'vision_mission';
        } elseif ($action === 'save_peo') {
            $peoCode = $_POST['peo_code'] ?? '';
            $title = $_POST['peo_title'] ?? '';
            $desc = $_POST['peo_description'] ?? '';
            $target = (float)($_POST['target_score'] ?? 2.00);
            $sort = (int)($_POST['sort_order'] ?? 1);
            $peoId = !empty($_POST['peo_id']) ? (int)$_POST['peo_id'] : null;
            $progId = !empty($_POST['program_id']) ? (int)$_POST['program_id'] : null;
            $effectiveYear = (int)($_POST['effective_from_year'] ?? 2020);

            $res = $batchService->saveMasterPEO($selectedDeptId, $progId, $peoCode, $title, $desc, $target, $sort, $peoId, $effectiveYear);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
            $activeTab = 'peos';
        } elseif ($action === 'save_batch_peo_target') {
            $peoId = (int)($_POST['peo_id'] ?? 0);
            $target = (float)($_POST['target_score'] ?? 2.00);
            if ($selectedBatchId > 0 && $peoId > 0) {
                $res = $batchService->saveBatchPEOTarget($selectedBatchId, $peoId, $target, $userId);
                if ($res['status'] === 1) {
                    $succMsg = $res['msg'];
                } else {
                    $errMsg = $res['err'];
                }
            } else {
                $errMsg = "Invalid batch or PEO selection for target override.";
            }
            $activeTab = 'peos';
        } elseif ($action === 'delete_peo') {
            $peoId = (int)($_POST['peo_id'] ?? 0);
            $res = $batchService->deleteMasterPEO($peoId, $selectedDeptId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
            $activeTab = 'peos';
        } elseif ($action === 'save_peo_mission_matrix') {
            $matrix = $_POST['matrix'] ?? [];
            $res = $batchService->savePeoMissionMatrix($selectedDeptId, $matrix, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
            $activeTab = 'peo_mission';
        } elseif ($action === 'save_po_peo_matrix') {
            $matrix = $_POST['matrix'] ?? [];
            $regId = $currentBatch ? (int)$currentBatch['regulation_id'] : null;
            $res = $batchService->savePoPeoMatrix($selectedDeptId, $matrix, $userId, $regId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
            $activeTab = 'po_peo';
        }
    }
}

// Generate CSRF token
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Load data for active selection
if ($selectedBatchId > 0) {
    // Cohort view: inherited data
    $vmData = $batchService->getBatchVisionMission($selectedBatchId, $selectedDeptId);
    $peos = $batchService->getBatchPEOs($selectedBatchId, $selectedDeptId);
    $peoMissionData = $batchService->getPeoMissionMatrix($selectedDeptId, null, $selectedBatchId);
    $poPeoData = $batchService->getPoPeoMatrix($selectedDeptId, null, $selectedBatchId);
    $macroAttainment = $batchService->getBatchMacroAttainment($selectedBatchId, $selectedDeptId);
} else {
    // Department Master view
    $vmData = $batchService->getMasterVisionMission($selectedDeptId);
    $peos = $batchService->getMasterPEOs($selectedDeptId);
    $peoMissionData = $batchService->getPeoMissionMatrix($selectedDeptId);
    $poPeoData = $batchService->getPoPeoMatrix($selectedDeptId);
    $macroAttainment = null;
}
?>

<div class="container my-4">
    <!-- Header Banner -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary fw-bold">
                        <i class="bi bi-diagram-3-fill me-2"></i> Master OBE Governance & Cohort Inheritance
                    </h4>
                    <p class="text-muted mb-0 small">
                        Institutional Standards: Vision &rarr; Mission &rarr; PEOs &rarr; POs/PSOs &rarr; Articulation. 
                        <strong>Batches inherit master statements by department and entry year without duplication.</strong>
                    </p>
                </div>
                <div class="mt-2 mt-md-0">
                    <a href="superadminbatches.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Batches
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (!empty($succMsg)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i> <?= htmlspecialchars($succMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (!empty($errMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($errMsg); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Scope Selector Hub -->
    <div class="card shadow-sm border-0 mb-4 bg-light">
        <div class="card-body py-3">
            <form method="GET" class="row g-3 align-items-center">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab); ?>">
                <div class="col-md-5">
                    <label class="form-label mb-1 fw-bold text-dark small"><i class="bi bi-building me-1"></i> Academic Department:</label>
                    <select name="dept_id" class="form-select" onchange="this.form.submit()">
                        <?php foreach ($allDepts as $d): ?>
                            <option value="<?= (int)$d['id']; ?>" <?= ($selectedDeptId === (int)$d['id']) ? 'selected' : ''; ?>>
                                <?= htmlspecialchars($d['dept_shortname'] . ' - ' . $d['dept_fullname']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-5">
                    <label class="form-label mb-1 fw-bold text-dark small"><i class="bi bi-people me-1"></i> Governance Scope / Cohort:</label>
                    <select name="batch_id" class="form-select" onchange="this.form.submit()">
                        <option value="0" <?= ($selectedBatchId === 0) ? 'selected' : ''; ?>>
                            ⭐ Department Master Standards (Stored Once, Inherited by All Batches)
                        </option>
                        <optgroup label="Student Cohorts / Batches">
                            <?php foreach ($allBatches as $b): ?>
                                <option value="<?= (int)$b['id']; ?>" <?= ($selectedBatchId === (int)$b['id']) ? 'selected' : ''; ?>>
                                    Cohort: <?= htmlspecialchars($b['batch_name'] . ' (' . $b['prog_shortname'] . ' - ' . $b['regulation'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
                <div class="col-md-2 mt-auto">
                    <button type="submit" class="btn btn-primary w-100 fw-semibold">
                        <i class="bi bi-arrow-repeat me-1"></i> View Scope
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Mode Indicator -->
    <?php if ($selectedBatchId > 0 && $currentBatch): ?>
        <div class="alert alert-info border-0 shadow-sm d-flex justify-content-between align-items-center py-2 px-3 mb-4">
            <div>
                <i class="bi bi-link-45deg fs-5 me-1 text-primary"></i>
                <strong>Viewing Cohort:</strong> <?= htmlspecialchars($currentBatch['batch_name']); ?> 
                <span class="badge bg-primary ms-1"><?= htmlspecialchars($currentBatch['prog_shortname']); ?></span>
                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($currentBatch['regulation']); ?></span>
                <span class="badge bg-light text-dark ms-2">Admitted: <?= (int)$currentBatch['admission_year']; ?></span>
                <span class="ms-2 text-muted small">Inheriting master curriculum standards for this cohort.</span>
            </div>
            <div>
                <a href="?dept_id=<?= $selectedDeptId; ?>&batch_id=0&tab=<?= htmlspecialchars($activeTab); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="bi bi-pencil-square me-1"></i> Edit Department Masters
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-success border-0 shadow-sm py-2 px-3 mb-4">
            <i class="bi bi-stars fs-5 me-1 text-success"></i>
            <strong>Editing Department Master Standards:</strong> Changes saved here are stored in master tables (`vision_mission`, `peos`, `peo_mission_mapping`, `po_peo_mapping`) and <strong>automatically inherited</strong> by all student batches of this department.
        </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills nav-fill mb-4 shadow-sm bg-white p-2 rounded border" id="obeTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($activeTab === 'vision_mission') ? 'active' : ''; ?>" 
               href="?dept_id=<?= $selectedDeptId; ?>&batch_id=<?= $selectedBatchId; ?>&tab=vision_mission">
                <i class="bi bi-eye me-1"></i> 1. Vision & Mission
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($activeTab === 'peos') ? 'active' : ''; ?>" 
               href="?dept_id=<?= $selectedDeptId; ?>&batch_id=<?= $selectedBatchId; ?>&tab=peos">
                <i class="bi bi-award me-1"></i> 2. PEOs
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($activeTab === 'peo_mission') ? 'active' : ''; ?>" 
               href="?dept_id=<?= $selectedDeptId; ?>&batch_id=<?= $selectedBatchId; ?>&tab=peo_mission">
                <i class="bi bi-grid-3x3 me-1"></i> 3. PEO &rarr; Mission Matrix
            </a>
        </li>
        <li class="nav-item" role="presentation">
            <a class="nav-link <?= ($activeTab === 'po_peo') ? 'active' : ''; ?>" 
               href="?dept_id=<?= $selectedDeptId; ?>&batch_id=<?= $selectedBatchId; ?>&tab=po_peo">
                <i class="bi bi-diagram-2 me-1"></i> 4. PO/PSO &rarr; PEO Matrix
            </a>
        </li>
        <?php if ($selectedBatchId > 0): ?>
            <li class="nav-item" role="presentation">
                <a class="nav-link <?= ($activeTab === 'attainment') ? 'active' : ''; ?>" 
                   href="?dept_id=<?= $selectedDeptId; ?>&batch_id=<?= $selectedBatchId; ?>&tab=attainment">
                    <i class="bi bi-graph-up-arrow me-1 text-warning"></i> 5. Cohort Macro Attainment
                </a>
            </li>
        <?php endif; ?>
    </ul>

    <!-- TAB 1: Vision & Mission -->
    <?php if ($activeTab === 'vision_mission'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-compass me-2 text-primary"></i> Master Vision & Mission Statements
                    </h5>
                    <p class="text-muted small mb-0">
                        <?= ($selectedBatchId > 0) ? 'Inherited by Cohort ' . htmlspecialchars($currentBatch['batch_name']) : 'Defined once at the department level; inherited across all cohorts.'; ?>
                    </p>
                </div>
                <?php if ($selectedBatchId > 0 && !empty($vmData['inherited_from'])): ?>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2">
                        <i class="bi bi-check2-circle me-1"></i> Inherited from <?= ucfirst($vmData['inherited_from']); ?> Master
                    </span>
                <?php endif; ?>
            </div>
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                    <input type="hidden" name="action" value="save_vision_mission">

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label fw-bold text-secondary">Effective From Year:</label>
                            <input type="number" name="effective_from_year" class="form-control" value="<?= (int)($vmData['effective_from_year'] ?? 2020); ?>" required>
                            <div class="form-text small">Applies to all batches admitted in or after this year.</div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary">
                            <i class="bi bi-stars me-1 text-warning"></i> Department Vision Statement:
                        </label>
                        <textarea name="vision_statement" rows="3" class="form-control" placeholder="Enter department vision statement..." required><?= htmlspecialchars($vmData['vision_statement'] ?? ''); ?></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary d-flex justify-content-between align-items-center">
                            <span><i class="bi bi-list-check me-1 text-info"></i> Department Mission Statements (M1, M2, M3...):</span>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="addMissionBtn">
                                <i class="bi bi-plus-lg me-1"></i> Add Mission Statement
                            </button>
                        </label>
                        <div id="missionsContainer">
                            <?php 
                            $missionsList = $vmData['missions_parsed'] ?? [];
                            if (empty($missionsList)) {
                                $missionsList = ['M1' => '', 'M2' => ''];
                            }
                            $mIdx = 1;
                            foreach ($missionsList as $mKey => $mVal): 
                            ?>
                                <div class="input-group mb-2 mission-row">
                                    <span class="input-group-text bg-light fw-bold">M<?= $mIdx; ?></span>
                                    <input type="text" name="missions[]" class="form-control" value="<?= htmlspecialchars($mVal); ?>" placeholder="Enter mission statement..." required>
                                    <button type="button" class="btn btn-outline-danger remove-mission-btn"><i class="bi bi-trash"></i></button>
                                </div>
                            <?php 
                                $mIdx++;
                            endforeach; 
                            ?>
                        </div>
                    </div>

                    <div class="mt-4 text-end">
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="bi bi-save me-1"></i> Save Master Vision & Mission
                        </button>
                    </div>
                </form>
            </div>
        </div>

    <!-- TAB 2: PEOs -->
    <?php elseif ($activeTab === 'peos'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 fw-bold text-dark">
                        <i class="bi bi-award me-2 text-primary"></i> Program Educational Objectives (PEOs)
                    </h5>
                    <p class="text-muted small mb-0">
                        <?= ($selectedBatchId > 0) ? 'Inherited for Batch ' . htmlspecialchars($currentBatch['batch_name']) . ' (Target scores can be cohort-customized below).' : 'Master PEOs defined once per program/department; inherited across cohorts.'; ?>
                    </p>
                </div>
                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPeoModal">
                    <i class="bi bi-plus-circle me-1"></i> Add Master PEO
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3" style="width: 10%;">Code</th>
                            <th style="width: 25%;">Title</th>
                            <th style="width: 40%;">Description</th>
                            <th style="width: 15%;">Target Score</th>
                            <th class="text-end pe-3" style="width: 10%;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($peos)): ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No PEOs configured for this department yet. Click "Add Master PEO" above.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($peos as $p): ?>
                                <tr>
                                    <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($p['peo_code']); ?></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($p['peo_title'] ?: '-'); ?></td>
                                    <td class="small text-secondary"><?= htmlspecialchars($p['peo_description']); ?></td>
                                    <td>
                                        <?php if ($selectedBatchId > 0): ?>
                                            <!-- Cohort custom target override form -->
                                            <form method="POST" class="d-flex align-items-center">
                                                <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                                                <input type="hidden" name="action" value="save_batch_peo_target">
                                                <input type="hidden" name="peo_id" value="<?= (int)$p['id']; ?>">
                                                <input type="number" step="0.05" min="1.0" max="3.0" name="target_score" class="form-control form-control-sm text-center fw-bold me-1" style="width: 75px;" value="<?= number_format((float)$p['target_score'], 2); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary" title="Save cohort override"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <?php if (!empty($p['is_custom_target'])): ?>
                                                <span class="badge bg-warning-subtle text-dark border border-warning-subtle small mt-1" style="font-size: 0.7rem;">Cohort Override</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted small mt-1" style="font-size: 0.7rem;">Inherited Master</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="badge bg-success"><?= number_format((float)$p['target_score'], 2); ?> / 3.0</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end pe-3">
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this Master PEO?');">
                                            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                                            <input type="hidden" name="action" value="delete_peo">
                                            <input type="hidden" name="peo_id" value="<?= (int)$p['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- TAB 3: PEO to Mission Articulation Matrix -->
    <?php elseif ($activeTab === 'peo_mission'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-grid-3x3 me-2 text-primary"></i> Master PEO &rarr; Mission Articulation Matrix
                </h5>
                <p class="text-muted small mb-0">Stored once in `peo_mission_mapping`. Correlation Weights: 3 = High, 2 = Medium, 1 = Low, 0 / Blank = No Correlation.</p>
            </div>
            <div class="card-body p-4">
                <?php 
                $matrixPeos = $peoMissionData['peos'] ?? [];
                $matrixMissions = $peoMissionData['missions'] ?? [];
                $curMatrix = $peoMissionData['matrix'] ?? [];
                ?>
                <?php if (empty($matrixPeos) || empty($matrixMissions)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle me-2"></i> Please configure both <strong>Mission Statements</strong> (Tab 1) and <strong>PEOs</strong> (Tab 2) before generating the articulation matrix.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                        <input type="hidden" name="action" value="save_peo_mission_matrix">

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-start ps-3" style="width: 25%;">PEO Statements</th>
                                        <?php foreach ($matrixMissions as $mKey => $mText): ?>
                                            <th title="<?= htmlspecialchars($mText); ?>">
                                                <?= htmlspecialchars($mKey); ?>
                                                <div class="small fw-normal text-muted" style="font-size: 0.75rem; max-width: 120px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?= htmlspecialchars($mText); ?>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matrixPeos as $peo): ?>
                                        <tr>
                                            <td class="text-start ps-3">
                                                <span class="fw-bold text-primary"><?= htmlspecialchars($peo['peo_code']); ?>:</span>
                                                <span class="small text-secondary"><?= htmlspecialchars($peo['peo_title'] ?: substr($peo['peo_description'], 0, 40) . '...'); ?></span>
                                            </td>
                                            <?php foreach ($matrixMissions as $mKey => $mText): 
                                                $val = $curMatrix[$peo['id']][$mKey] ?? 0;
                                            ?>
                                                <td>
                                                    <select name="matrix[<?= (int)$peo['id']; ?>][<?= htmlspecialchars($mKey); ?>]" class="form-select form-select-sm text-center fw-bold <?= ($val > 0) ? 'bg-light text-primary border-primary' : ''; ?>">
                                                        <option value="0" <?= ($val === 0) ? 'selected' : ''; ?>>-</option>
                                                        <option value="1" <?= ($val === 1) ? 'selected' : ''; ?>>1</option>
                                                        <option value="2" <?= ($val === 2) ? 'selected' : ''; ?>>2</option>
                                                        <option value="3" <?= ($val === 3) ? 'selected' : ''; ?>>3</option>
                                                    </select>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Save Master PEO-Mission Articulation Matrix
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    <!-- TAB 4: PO/PSO to PEO Articulation Matrix -->
    <?php elseif ($activeTab === 'po_peo'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="card-title mb-0 fw-bold text-dark">
                    <i class="bi bi-diagram-2 me-2 text-primary"></i> Master Program Outcomes (PO/PSO) &rarr; PEO Articulation Matrix
                </h5>
                <p class="text-muted small mb-0">Stored once in `po_peo_mapping`. Mapping NBA Graduate Attributes (PO1 to PO12) and Department PSOs to PEOs.</p>
            </div>
            <div class="card-body p-4">
                <?php 
                $matrixPos = $poPeoData['pos'] ?? [];
                $matrixPeos = $poPeoData['peos'] ?? [];
                $curPoMatrix = $poPeoData['matrix'] ?? [];
                ?>
                <?php if (empty($matrixPos) || empty($matrixPeos)): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-info-circle me-2"></i> Please ensure both <strong>POs/PSOs</strong> (under `po_pso` table) and <strong>PEOs</strong> (Tab 2) are defined for this department.
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                        <input type="hidden" name="action" value="save_po_peo_matrix">

                        <div class="table-responsive">
                            <table class="table table-bordered align-middle text-center">
                                <thead class="table-light">
                                    <tr>
                                        <th class="text-start ps-3" style="width: 35%;">Graduate Attribute / Outcome</th>
                                        <?php foreach ($matrixPeos as $peo): ?>
                                            <th>
                                                <div class="fw-bold text-primary"><?= htmlspecialchars($peo['peo_code']); ?></div>
                                                <div class="small fw-normal text-muted" style="font-size: 0.75rem; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                    <?= htmlspecialchars($peo['peo_title'] ?: $peo['peo_code']); ?>
                                                </div>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($matrixPos as $p): ?>
                                        <tr>
                                            <td class="text-start ps-3">
                                                <span class="fw-bold text-dark"><?= htmlspecialchars($p['code']); ?>:</span>
                                                <span class="small text-secondary"><?= htmlspecialchars(substr($p['description'], 0, 50) . '...'); ?></span>
                                            </td>
                                            <?php foreach ($matrixPeos as $peo): 
                                                $val = $curPoMatrix[$p['id']][$peo['id']] ?? 0;
                                            ?>
                                                <td>
                                                    <select name="matrix[<?= (int)$p['id']; ?>][<?= (int)$peo['id']; ?>]" class="form-select form-select-sm text-center fw-bold <?= ($val > 0) ? 'bg-light text-primary border-primary' : ''; ?>">
                                                        <option value="0" <?= ($val === 0) ? 'selected' : ''; ?>>-</option>
                                                        <option value="1" <?= ($val === 1) ? 'selected' : ''; ?>>1</option>
                                                        <option value="2" <?= ($val === 2) ? 'selected' : ''; ?>>2</option>
                                                        <option value="3" <?= ($val === 3) ? 'selected' : ''; ?>>3</option>
                                                    </select>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 text-end">
                            <button type="submit" class="btn btn-primary px-4 fw-bold">
                                <i class="bi bi-save me-1"></i> Save Master PO-PEO Articulation Matrix
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>

    <!-- TAB 5: Cohort Macro Attainment -->
    <?php elseif ($activeTab === 'attainment' && $selectedBatchId > 0 && !empty($macroAttainment)): ?>
        <div class="row g-4">
            <!-- Mission Level Summary -->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-compass me-2 text-danger"></i> Level 1: Mission Attainment (Backtracked)
                        </h5>
                        <span class="badge bg-primary px-3 py-2">Batch: <?= htmlspecialchars($currentBatch['batch_name'] ?? ''); ?></span>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($macroAttainment['missions'])): ?>
                            <p class="text-muted mb-0">No mission statements configured or mapped yet.</p>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($macroAttainment['missions'] as $m): ?>
                                    <div class="col-md-6 col-lg-3">
                                        <div class="border rounded p-3 bg-light h-100">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="badge bg-danger fs-6"><?= htmlspecialchars($m['key']); ?></span>
                                                <span class="badge bg-<?= ($m['status'] === 'ACHIEVED') ? 'success' : 'warning text-dark'; ?>">
                                                    <?= $m['status']; ?>
                                                </span>
                                            </div>
                                            <p class="small text-secondary mb-2" style="height: 40px; overflow: hidden;">
                                                <?= htmlspecialchars($m['text']); ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="small fw-bold">Score: <?= $m['attained']; ?> / 3.0</span>
                                                <span class="fw-bold text-primary"><?= $m['percentage']; ?>%</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 6px;">
                                                <div class="progress-bar bg-<?= ($m['status'] === 'ACHIEVED') ? 'success' : 'warning'; ?>" 
                                                     role="progressbar" style="width: <?= $m['percentage']; ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- PEO Level Summary -->
            <div class="col-12">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3 border-bottom">
                        <h5 class="card-title mb-0 fw-bold text-dark">
                            <i class="bi bi-award me-2 text-primary"></i> Level 2: Program Educational Objectives (PEO) Attainment
                        </h5>
                    </div>
                    <div class="card-body p-4">
                        <?php if (empty($macroAttainment['peos'])): ?>
                            <p class="text-muted mb-0">No PEOs configured yet.</p>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($macroAttainment['peos'] as $peo): ?>
                                    <div class="col-md-4">
                                        <div class="card border h-100 p-3 shadow-none">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <span class="fw-bold text-primary fs-5"><?= htmlspecialchars($peo['code']); ?></span>
                                                <span class="badge bg-<?= ($peo['status'] === 'ATTAINED') ? 'success' : 'warning text-dark'; ?>">
                                                    <?= $peo['status']; ?>
                                                </span>
                                            </div>
                                            <h6 class="fw-semibold text-dark mb-1"><?= htmlspecialchars($peo['title'] ?: $peo['code']); ?></h6>
                                            <div class="d-flex justify-content-between text-muted small mt-2">
                                                <span>Target: <?= $peo['target']; ?></span>
                                                <span class="fw-bold text-dark">Attained: <?= $peo['attained']; ?> (<?= $peo['percentage']; ?>%)</span>
                                            </div>
                                            <div class="progress mt-1" style="height: 8px;">
                                                <div class="progress-bar bg-<?= ($peo['status'] === 'ATTAINED') ? 'primary' : 'warning'; ?>" 
                                                     role="progressbar" style="width: <?= min(100.0, $peo['percentage']); ?>%"></div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Traceability Info Card -->
            <div class="col-12">
                <div class="alert alert-info border-0 shadow-sm d-flex align-items-center p-3 mb-0">
                    <i class="bi bi-lightbulb-fill text-warning fs-3 me-3"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Full-Cycle Backtracking & Level-Wise Audit</h6>
                        <p class="mb-0 small">
                            Scores automatically flow from student semester coursework (CIA + Exam Results) &rarr; Course Outcomes (COs) &rarr; POs/PSOs via Course Articulation &rarr; PEOs via PO-PEO matrix &rarr; Department Mission. Batch inheritance guarantees zero data duplication.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Add Master PEO -->
<div class="modal fade" id="addPeoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="save_peo">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> Add Master Program Educational Objective</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">PEO Code <span class="text-danger">*</span></label>
                        <input type="text" name="peo_code" class="form-control" placeholder="e.g. PEO1" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Sort Order</label>
                        <input type="number" name="sort_order" class="form-control" value="<?= count($peos) + 1; ?>">
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Effective From Year</label>
                        <input type="number" name="effective_from_year" class="form-control" value="2020" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Target Score</label>
                        <input type="number" step="0.05" name="target_score" class="form-control" value="2.00" min="1.0" max="3.0">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Title / Headline</label>
                    <input type="text" name="peo_title" class="form-control" placeholder="e.g. Professional Core Competence">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea name="peo_description" rows="3" class="form-control" placeholder="Describe the expected capability of graduates..." required></textarea>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Save Master PEO</button>
            </div>
        </form>
    </div>
</div>

<script>
// Dynamic mission statement row adder
document.getElementById('addMissionBtn')?.addEventListener('click', function() {
    const container = document.getElementById('missionsContainer');
    const rowCount = container.querySelectorAll('.mission-row').length + 1;
    const div = document.createElement('div');
    div.className = 'input-group mb-2 mission-row';
    div.innerHTML = `
        <span class="input-group-text bg-light fw-bold">M${rowCount}</span>
        <input type="text" name="missions[]" class="form-control" placeholder="Enter mission statement..." required>
        <button type="button" class="btn btn-outline-danger remove-mission-btn"><i class="bi bi-trash"></i></button>
    `;
    container.appendChild(div);
});

document.addEventListener('click', function(e) {
    if (e.target.closest('.remove-mission-btn')) {
        const rows = document.querySelectorAll('.mission-row');
        if (rows.length > 1) {
            e.target.closest('.mission-row').remove();
            // Reindex labels
            document.querySelectorAll('.mission-row').forEach((row, i) => {
                row.querySelector('.input-group-text').textContent = `M${i + 1}`;
            });
        } else {
            alert('At least one mission statement is required.');
        }
    }
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
