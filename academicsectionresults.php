<?php
/**
 * Controller: Academic Section & Examination Results Publication Management
 * 
 * Allows Academic Section / Admin to create exam notifications, upload
 * official examination result CSVs, and publish grade cards to students.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';
if (!in_array($role, ['superadmin', 'admin', 'academic_section'], true)) {
    header('Location: ./');
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_RESULTS_PUBLISH', $role);

$page_title = "Exam Results Management";

require_once __DIR__ . '/header.php';

// Render appropriate role menu
if ($role === 'superadmin') {
    require_once __DIR__ . '/superadminmenu.php';
} elseif ($role === 'admin') {
    require_once __DIR__ . '/adminmenu.php';
} else {
    require_once __DIR__ . '/academicsectionmenu.php';
}

require_once __DIR__ . '/services/ExamResultsService.php';
require_once __DIR__ . '/programs.class.php';
require_once __DIR__ . '/regulations.class.php';
require_once __DIR__ . '/academicyears.class.php';

$examService = \Services\ExamResultsService::getInstance();
$programsObj = new \Programs();
$regulationsObj = new \Regulations();
$acadYearsObj = new \AcademicYears();

$programs = $programsObj->getAllPrograms()['data'] ?? [];
$regulations = $regulationsObj->getAllRegulations()['data'] ?? [];
$acadYears = $acadYearsObj->getActiveAcademicYears()['data'] ?? [];

$succMsg = '';
$errMsg = '';

// Handle POST actions
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    \FeatureManager::requireWriteAccess('MOD_RESULTS_PUBLISH');
    $token = $_POST['secretcode'] ?? '';
    if (empty($token) || empty($_SESSION['secretcode']) || !hash_equals($_SESSION['secretcode'], $token)) {
        $errMsg = "Security validation failed (CSRF mismatch).";
    } else {
        $action = $_POST['action'] ?? '';
        $userId = (int)($_SESSION['userid'] ?? $_SESSION['user_id'] ?? 1);

        if ($action === 'create_notification') {
            $res = $examService->createNotification($_POST, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
        } elseif ($action === 'upload_results_csv') {
            $notifId = (int)($_POST['notification_id'] ?? 0);
            if ($notifId <= 0) {
                $errMsg = "Invalid notification selected.";
            } elseif (empty($_FILES['results_csv']['tmp_name'])) {
                $errMsg = "Please select a CSV file to upload.";
            } else {
                $res = $examService->importResultsCsv($notifId, $_FILES['results_csv']['tmp_name'], $userId);
                if ($res['status'] === 1) {
                    $succMsg = $res['msg'];
                    if (!empty($res['errors'])) {
                        $errMsg = "Imported with warnings:<br>" . implode("<br>", array_slice($res['errors'], 0, 5));
                    }
                } else {
                    $errMsg = $res['err'];
                }
            }
        } elseif ($action === 'toggle_publish') {
            $notifId = (int)($_POST['notification_id'] ?? 0);
            $newStatus = (int)($_POST['new_status'] ?? 0);
            $res = $examService->togglePublishStatus($notifId, $newStatus, $userId);
            if ($res['status'] === 1) {
                $succMsg = $res['msg'];
            } else {
                $errMsg = $res['err'];
            }
        }
    }
}

// Generate CSRF token
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

$notifications = $examService->getNotifications();
?>

<div class="container my-4">
    <!-- Header Card -->
    <div class="card shadow-sm border-0 mb-4 bg-white">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <div>
                    <h4 class="mb-1 text-primary fw-bold">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2"></i> Semester Exam Results Publication
                    </h4>
                    <p class="text-muted mb-0 small">
                        Upload official examination results CSVs, publish grade cards to students, and auto-feed SEE marks into OBE attainment.
                    </p>
                </div>
                <div class="mt-2 mt-md-0 d-flex gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createNotifModal">
                        <i class="bi bi-plus-circle me-1"></i> New Exam Notification
                    </button>
                    <a href="data:text/csv;charset=utf-8,HTNO,SubCode,SubName,Internals,Externals,Total,Grade,GradePoints,Credits,Status%0A21021A0501,20A05301T,Operating Systems,28,45,73,A,8,3,PASS" 
                       download="sample_exam_results_template.csv" class="btn btn-outline-secondary">
                        <i class="bi bi-download me-1"></i> Sample CSV Template
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
            <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $errMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- Notifications Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3 border-0 d-flex justify-content-between align-items-center">
            <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-megaphone-fill me-2"></i> Published Examination Notifications</h6>
            <span class="badge bg-secondary"><?= count($notifications); ?> Notifications</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-3">#</th>
                        <th>Notification Code & Title</th>
                        <th>Program / Reg</th>
                        <th>Year-Sem</th>
                        <th>Month-Year</th>
                        <th>Results Uploaded</th>
                        <th>Status</th>
                        <th class="text-end pe-3">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($notifications)): ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">No examination notifications created yet. Click "New Exam Notification" to get started.</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($notifications as $idx => $n): ?>
                            <tr>
                                <td class="ps-3 text-muted"><?= $idx + 1; ?></td>
                                <td>
                                    <div class="fw-bold text-primary"><?= htmlspecialchars($n['notification_code']); ?></div>
                                    <div class="small text-secondary"><?= htmlspecialchars($n['title']); ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= htmlspecialchars($n['prog_shortname']); ?></span>
                                    <span class="badge bg-info text-dark"><?= htmlspecialchars($n['regulation']); ?></span>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($n['yearsem']); ?></span></td>
                                <td><?= htmlspecialchars($n['month_year']); ?></td>
                                <td>
                                    <span class="fw-bold text-dark"><?= (int)$n['results_count']; ?> records</span>
                                    <span class="text-muted small">(<?= (int)$n['students_count']; ?> students)</span>
                                </td>
                                <td>
                                    <?php if ($n['is_published']): ?>
                                        <span class="badge bg-success"><i class="bi bi-eye me-1"></i> Published</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><i class="bi bi-eye-slash me-1"></i> Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-outline-primary upload-csv-btn me-1" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#uploadCsvModal"
                                            data-id="<?= (int)$n['id']; ?>"
                                            data-title="<?= htmlspecialchars($n['title']); ?>"
                                            title="Upload Results CSV">
                                        <i class="bi bi-upload"></i> Upload CSV
                                    </button>

                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
                                        <input type="hidden" name="action" value="toggle_publish">
                                        <input type="hidden" name="notification_id" value="<?= (int)$n['id']; ?>">
                                        <input type="hidden" name="new_status" value="<?= $n['is_published'] ? 0 : 1; ?>">
                                        <button type="submit" class="btn btn-sm <?= $n['is_published'] ? 'btn-outline-warning' : 'btn-outline-success'; ?>" 
                                                title="<?= $n['is_published'] ? 'Unpublish from student view' : 'Publish to students'; ?>">
                                            <?= $n['is_published'] ? '<i class="bi bi-eye-slash"></i>' : '<i class="bi bi-eye"></i>'; ?>
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Create Notification -->
<div class="modal fade" id="createNotifModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="create_notification">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i> New Exam Notification</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notification Code <span class="text-danger">*</span></label>
                    <input type="text" name="notification_code" class="form-control" placeholder="e.g. BTECH_R20_3_1_NOV2025" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Notification Title <span class="text-danger">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. B.Tech III Year I Sem (R20) Regular & Supple Exams - Nov 2025" required>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Degree Program <span class="text-danger">*</span></label>
                        <select name="program_id" class="form-select" required>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= (int)$p['id']; ?>"><?= htmlspecialchars($p['prog_shortname']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Regulation <span class="text-danger">*</span></label>
                        <select name="regulation_id" class="form-select" required>
                            <?php foreach ($regulations as $r): ?>
                                <option value="<?= (int)$r['id']; ?>"><?= htmlspecialchars($r['regulation']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Academic Year <span class="text-danger">*</span></label>
                        <select name="academic_year" class="form-select" required>
                            <?php foreach ($acadYears as $ay): ?>
                                <option value="<?= htmlspecialchars($ay['acad_year']); ?>"><?= htmlspecialchars($ay['acad_year']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Year-Semester <span class="text-danger">*</span></label>
                        <input type="text" name="yearsem" class="form-control" placeholder="e.g. III Yr - I Sem" required>
                    </div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Month & Year <span class="text-danger">*</span></label>
                        <input type="text" name="month_year" class="form-control" placeholder="e.g. November 2025" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Release Date</label>
                        <input type="date" name="release_date" class="form-control" value="<?= date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="mb-3">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_published" value="1" id="publishCheck" checked>
                        <label class="form-check-label fw-semibold" for="publishCheck">Publish to student view immediately</label>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary fw-bold"><i class="bi bi-save me-1"></i> Save Notification</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Upload Results CSV -->
<div class="modal fade" id="uploadCsvModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow">
            <input type="hidden" name="secretcode" value="<?= htmlspecialchars($_SESSION['secretcode']); ?>">
            <input type="hidden" name="action" value="upload_results_csv">
            <input type="hidden" name="notification_id" id="uploadNotifId">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-upload me-2"></i> Upload Results CSV</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="alert alert-light border mb-3">
                    <div class="small fw-bold text-dark">Target Notification:</div>
                    <div id="uploadNotifTitle" class="text-primary fw-semibold small"></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select Results CSV File <span class="text-danger">*</span></label>
                    <input type="file" name="results_csv" accept=".csv" class="form-control" required>
                    <div class="form-text small">
                        Required columns: <code>HTNO</code>, <code>SubCode</code>. Optional: <code>SubName</code>, <code>Internals</code>, <code>Externals</code>, <code>Total</code>, <code>Grade</code>, <code>GradePoints</code>, <code>Credits</code>, <code>Status</code>.
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-dark fw-bold"><i class="bi bi-cloud-upload me-1"></i> Ingest & Publish Results</button>
            </div>
        </form>
    </div>
</div>

<script>
document.querySelectorAll('.upload-csv-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('uploadNotifId').value = this.dataset.id;
        document.getElementById('uploadNotifTitle').textContent = this.dataset.title;
    });
});
</script>

<?php require_once __DIR__ . '/footer.php'; ?>
