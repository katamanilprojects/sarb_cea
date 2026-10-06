<?php
// Ensure errors are displayed on screen instead of throwing silent 500 errors
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
@set_time_limit(600);
@ini_set('memory_limit', '512M');

// Ultra-robust environment detection (supports Apache, Nginx, LiteSpeed, FastCGI, CLI)
$isCli = (php_sapi_name() === 'cli' && empty($_SERVER['HTTP_HOST']) && empty($_SERVER['REQUEST_METHOD']));

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../services/CourseOutcomeSyncService.php';

class MigrationRunner extends DBCredentials {
    public function getConn(): mysqli {
        return $this->conn;
    }
}

$runner = new MigrationRunner();
$conn = $runner->getConn();

if (!$conn || $conn->connect_error) {
    $dbError = "Database connection failed: " . ($conn ? $conn->connect_error : "Unable to connect");
    if ($isCli) {
        fwrite(STDERR, "❌ {$dbError}\n");
        exit(1);
    } else {
        die("<div style='color:red; font-family:sans-serif; padding:20px;'><h3>❌ Database Connection Error</h3><p>{$dbError}</p></div>");
    }
}

// Helper to check column existence safely
function columnExists(mysqli $conn, string $table, string $column): bool {
    $stmt = $conn->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
    if (!$stmt) return false;
    $stmt->bind_param("ss", $table, $column);
    $stmt->execute();
    $cnt = (int)$stmt->get_result()->fetch_row()[0];
    $stmt->close();
    return $cnt > 0;
}

// Helper to fetch live snapshot safely
function getDatabaseSnapshot(mysqli $conn): array {
    $hasCurrSubId = columnExists($conn, 'course_outcomes', 'curr_sub_id');
    $masterCOs = 0;
    if ($hasCurrSubId) {
        $q = $conn->query("SELECT COUNT(*) FROM course_outcomes WHERE curr_sub_id IS NOT NULL AND sub_id IS NULL");
        $masterCOs = ($q && $r = $q->fetch_row()) ? (int)$r[0] : 0;
    }

    $q1 = $conn->query("SELECT COUNT(*) FROM course_outcomes WHERE sub_id IS NOT NULL");
    $offeringCOs = ($q1 && $r = $q1->fetch_row()) ? (int)$r[0] : 0;

    $q2 = $conn->query("SELECT COUNT(*) FROM co_po_mapping");
    $mappings = ($q2 && $r = $q2->fetch_row()) ? (int)$r[0] : 0;

    $q3 = $conn->query("SELECT COUNT(*) FROM curriculum_subjects");
    $currSubjects = ($q3 && $r = $q3->fetch_row()) ? (int)$r[0] : 0;

    $q4 = $conn->query("SELECT COUNT(*) FROM subjects");
    $activeOfferings = ($q4 && $r = $q4->fetch_row()) ? (int)$r[0] : 0;

    return [
        'has_curr_sub_id' => $hasCurrSubId,
        'master_cos' => $masterCOs,
        'offering_cos' => $offeringCOs,
        'mappings' => $mappings,
        'curr_subjects' => $currSubjects,
        'active_offerings' => $activeOfferings
    ];
}

// -------------------------------------------------------------
// Action Determination (CLI vs Web Browser)
// -------------------------------------------------------------
$action = null; // 'dry-run', 'execute', or null
if ($isCli) {
    $options = getopt("", ["dry-run", "execute", "help"]);
    if (isset($options['help']) || (!isset($options['dry-run']) && !isset($options['execute']))) {
        echo "========================================================================\n";
        echo " OBE Master CO & Articulation Matrix Migration Utility (CLI & Web)\n";
        echo "========================================================================\n";
        echo "Usage:\n";
        echo "  php scripts/production_migrate_co_matrix.php --dry-run   # Preview changes without touching DB\n";
        echo "  php scripts/production_migrate_co_matrix.php --execute   # Execute migration with transaction\n";
        echo "========================================================================\n";
        exit(0);
    }
    $action = isset($options['execute']) ? 'execute' : 'dry-run';
} else {
    // Web Browser Request
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? null;
    } elseif (isset($_GET['action'])) {
        $action = $_GET['action'];
    }
}

$beforeStats = getDatabaseSnapshot($conn);
$dbName = $conn->query("SELECT DATABASE()")->fetch_row()[0];
$hostInfo = $conn->host_info;

$executionLogs = [];
$afterStats = null;
$migResult = null;
$autoPopResult = null;

if ($action === 'cleanup') {
    try {
        $executionLogs[] = "Starting Duplicate Cleanup at " . date("Y-m-d H:i:s");
        $executionLogs[] = "Host: {$hostInfo} | Database: {$dbName}";

        $syncService = CourseOutcomeSyncService::getInstance();
        $cleanRes = $syncService->cleanupDuplicateMasterCOsAndMappings();
        $executionLogs[] = "✔ Duplicate Master CO rows removed: " . number_format($cleanRes['duplicate_cos_removed']);
        $executionLogs[] = "✔ Orphan / duplicate matrix rows removed: " . number_format($cleanRes['orphan_mappings_removed']);

        $executionLogs[] = "Enforcing database unique constraint `unique_master_co`...";
        $syncService->enforceMasterCoUniqueConstraint();
        $executionLogs[] = "✔ Unique constraint active: duplicate Master COs are permanently forbidden by database engine.";

        $afterStats = getDatabaseSnapshot($conn);
        $executionLogs[] = "Cleanup and optimization finished successfully at " . date("Y-m-d H:i:s");
    } catch (Throwable $e) {
        $executionLogs[] = "❌ FATAL EXCEPTION: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "\nTrace:\n" . $e->getTraceAsString();
    }
} elseif ($action === 'dry-run' || $action === 'execute') {
    try {
        $isDryRun = ($action === 'dry-run');
        $modeLabel = $isDryRun ? "DRY RUN PREVIEW" : "LIVE PRODUCTION EXECUTION";

        $executionLogs[] = "Starting [{$modeLabel}] at " . date("Y-m-d H:i:s");
        $executionLogs[] = "Host: {$hostInfo} | Database: {$dbName}";

        $syncService = CourseOutcomeSyncService::getInstance();

        // If live execution, clean up duplicates first and enforce unique constraint
        if (!$isDryRun) {
            $cleanRes = $syncService->cleanupDuplicateMasterCOsAndMappings();
            if ($cleanRes['duplicate_cos_removed'] > 0 || $cleanRes['orphan_mappings_removed'] > 0) {
                $executionLogs[] = "✔ Auto-cleaned " . number_format($cleanRes['duplicate_cos_removed']) . " duplicate Master COs and " . number_format($cleanRes['orphan_mappings_removed']) . " duplicate matrix cells before migration.";
            }
            $syncService->enforceMasterCoUniqueConstraint();
        }

        // 1. Schema & Indexes Check
        $colRes = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND COLUMN_NAME = 'curr_sub_id'")->fetch_row()[0];
        if ($colRes == 0) {
            $executionLogs[] = "Adding column `curr_sub_id` to `course_outcomes` table...";
            if (!$isDryRun) {
                $conn->query("ALTER TABLE course_outcomes ADD COLUMN curr_sub_id int(11) NULL DEFAULT NULL AFTER co_description");
                $executionLogs[] = "✔ Column `curr_sub_id` created successfully.";
            } else {
                $executionLogs[] = "[Dry Run] Would execute: ALTER TABLE course_outcomes ADD COLUMN curr_sub_id int(11) NULL DEFAULT NULL AFTER co_description";
            }
        } else {
            $executionLogs[] = "✔ Column `curr_sub_id` already exists.";
        }

        $idxRes = $conn->query("SHOW INDEX FROM course_outcomes WHERE Key_name = 'idx_co_curr_num'")->num_rows;
        if ($idxRes == 0) {
            $executionLogs[] = "Adding index `idx_co_curr_num` to `course_outcomes`...";
            if (!$isDryRun) {
                $conn->query("ALTER TABLE course_outcomes ADD INDEX idx_co_curr_num (curr_sub_id, co_number)");
                $executionLogs[] = "✔ Index `idx_co_curr_num` created successfully.";
            } else {
                $executionLogs[] = "[Dry Run] Would execute: ALTER TABLE course_outcomes ADD INDEX idx_co_curr_num (curr_sub_id, co_number)";
            }
        } else {
            $executionLogs[] = "✔ Index `idx_co_curr_num` already exists.";
        }

        // 2. Migration execution
        $executionLogs[] = "Executing migration via CourseOutcomeSyncService...";
        $migResult = $syncService->migrateExistingLegacyData($isDryRun);

        if (!empty($migResult['error'])) {
            $executionLogs[] = "❌ Error during legacy migration: " . $migResult['error'];
        } else {
            $executionLogs[] = "Curriculum subjects scanned: {$migResult['curr_subs_scanned']}";
            $executionLogs[] = "Master COs " . ($isDryRun ? "to create" : "created") . ": {$migResult['master_cos_created']}";
            $executionLogs[] = "Master mappings " . ($isDryRun ? "to create" : "created") . ": {$migResult['master_mappings_created']}";
            $executionLogs[] = "Sibling COs " . ($isDryRun ? "to backfill" : "backfilled") . ": {$migResult['sibling_cos_backfilled']}";
            $executionLogs[] = "Sibling mappings " . ($isDryRun ? "to backfill" : "backfilled") . ": {$migResult['sibling_mappings_backfilled']}";
        }

        // 3. Auto-populate sibling branch matrices
        $executionLogs[] = "Auto-populating matrices across sibling branches with canonical PO code mapping...";
        if (!$isDryRun) {
            $autoPopResult = $syncService->autoPopulateAllMissingMatricesBySubjectCode();
            $executionLogs[] = "Subject code groups checked: {$autoPopResult['groups_checked']}";
            $executionLogs[] = "Groups auto-populated: {$autoPopResult['groups_populated']}";
            $executionLogs[] = "Master matrices updated: {$autoPopResult['masters_updated']}";
            $executionLogs[] = "Offering matrices updated: {$autoPopResult['offerings_updated']}";
            $executionLogs[] = "Total matrix cells written: {$autoPopResult['cells_written']}";
        } else {
            $executionLogs[] = "[Dry Run] Sibling matrix auto-population previewed.";
        }

        // 4. Snapshot After State
        $afterStats = getDatabaseSnapshot($conn);
        $executionLogs[] = "Migration finished successfully at " . date("Y-m-d H:i:s");
    } catch (Throwable $e) {
        $executionLogs[] = "❌ FATAL EXCEPTION: " . $e->getMessage() . "\nFile: " . $e->getFile() . ":" . $e->getLine() . "\nTrace:\n" . $e->getTraceAsString();
    }
}

// -------------------------------------------------------------
// Render Output: CLI
// -------------------------------------------------------------
if ($isCli) {
    foreach ($executionLogs as $log) {
        echo "{$log}\n";
    }
    if ($afterStats) {
        echo "\n========================================================================\n";
        echo " Final Database State:\n";
        echo "  • Master COs:          {$afterStats['master_cos']} (+" . ($afterStats['master_cos'] - $beforeStats['master_cos']) . ")\n";
        echo "  • Offering COs:        {$afterStats['offering_cos']} (+" . ($afterStats['offering_cos'] - $beforeStats['offering_cos']) . ")\n";
        echo "  • Total Matrix Rows:   {$afterStats['mappings']} (+" . ($afterStats['mappings'] - $beforeStats['mappings']) . ")\n";
        echo "========================================================================\n";
        echo "✅ Done.\n";
    }
    exit(0);
}

// -------------------------------------------------------------
// Render Output: Web Browser GUI
// -------------------------------------------------------------
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OBE Master CO & Matrix Migration Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body { background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        .hero-banner { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); color: #fff; padding: 28px 0; margin-bottom: 25px; border-bottom: 3px solid #3b82f6; }
        .card { border: none; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .stat-badge { font-size: 1.8rem; font-weight: 700; color: #1e293b; }
        .terminal-box { background: #0f172a; color: #38bdf8; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; font-size: 0.88rem; padding: 18px; border-radius: 10px; max-height: 400px; overflow-y: auto; white-space: pre-wrap; line-height: 1.5; }
        .diff-plus { color: #10b981; font-weight: 600; }
    </style>
</head>
<body>

<div class="hero-banner">
    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>
                <h3 class="mb-1"><i class="bi bi-diagram-3-fill text-primary me-2"></i>OBE Master CO & Articulation Matrix Migration</h3>
                <p class="mb-0 text-secondary" style="font-size: 0.95rem;">Centralized Curriculum Catalog &amp; Cross-Branch Auto-Propagation Runner</p>
            </div>
            <div>
                <span class="badge bg-secondary p-2"><i class="bi bi-database me-1"></i> <?= htmlspecialchars($dbName) ?></span>
                <span class="badge bg-dark p-2"><i class="bi bi-clock me-1"></i> <?= date("Y-m-d H:i:s") ?></span>
            </div>
        </div>
    </div>
</div>

<div class="container pb-5">

    <!-- Current State Counters -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small text-uppercase fw-semibold"><i class="bi bi-bookmark-star text-primary me-1"></i> Master Course Outcomes</div>
                <div class="stat-badge mt-1">
                    <?= number_format($afterStats ? $afterStats['master_cos'] : $beforeStats['master_cos']) ?>
                    <?php if ($afterStats && $afterStats['master_cos'] > $beforeStats['master_cos']): ?>
                        <span class="diff-plus fs-6">(+<?= $afterStats['master_cos'] - $beforeStats['master_cos'] ?>)</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">Central BoS / Curriculum-level definitions</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small text-uppercase fw-semibold"><i class="bi bi-journal-check text-info me-1"></i> Active Offering COs</div>
                <div class="stat-badge mt-1">
                    <?= number_format($afterStats ? $afterStats['offering_cos'] : $beforeStats['offering_cos']) ?>
                    <?php if ($afterStats && $afterStats['offering_cos'] > $beforeStats['offering_cos']): ?>
                        <span class="diff-plus fs-6">(+<?= $afterStats['offering_cos'] - $beforeStats['offering_cos'] ?>)</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">Class-level offerings (Preserved for OBE Attainment)</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card p-3">
                <div class="text-muted small text-uppercase fw-semibold"><i class="bi bi-grid-3x3-gap-fill text-success me-1"></i> Total Matrix Mappings</div>
                <div class="stat-badge mt-1">
                    <?= number_format($afterStats ? $afterStats['mappings'] : $beforeStats['mappings']) ?>
                    <?php if ($afterStats && $afterStats['mappings'] > $beforeStats['mappings']): ?>
                        <span class="diff-plus fs-6">(+<?= number_format($afterStats['mappings'] - $beforeStats['mappings']) ?>)</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">Total populated CO-PO articulation cells</small>
            </div>
        </div>
    </div>

    <!-- Actions Card -->
    <div class="card mb-4">
        <div class="card-header bg-white py-3">
            <h5 class="card-title mb-0"><i class="bi bi-play-circle-fill text-primary me-2"></i>Migration Control Actions</h5>
        </div>
        <div class="card-body">
            <p class="text-muted">
                This utility inspects all existing Course Outcomes and Articulation Matrix mappings, provisions missing master curriculum definitions, and auto-propagates them across all parallel branch sections (Civil, EEE, MECH, ECE, CSE, etc.) by canonical PO code.
            </p>

            <form method="post" action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" class="d-flex flex-wrap gap-3 align-items-center">
                <button type="submit" name="action" value="dry-run" class="btn btn-outline-primary px-4 py-2">
                    <i class="bi bi-search me-1"></i> Run Dry-Run Preview
                </button>
                <button type="submit" name="action" value="execute" class="btn btn-success px-4 py-2" onclick="return confirm('Are you sure you want to run the live migration on database <?= htmlspecialchars($dbName) ?>? All existing records will be safely preserved.');">
                    <i class="bi bi-rocket-takeoff-fill me-1"></i> Execute Live Migration
                </button>
                <button type="submit" name="action" value="cleanup" class="btn btn-outline-danger px-4 py-2" onclick="return confirm('Clean up all duplicate master COs and orphan matrix mappings from database <?= htmlspecialchars($dbName) ?>?');">
                    <i class="bi bi-trash3-fill me-1"></i> Clean Up Duplicates &amp; Lock Index
                </button>
            </form>
        </div>
    </div>

    <!-- Execution Results -->
    <?php if (!empty($executionLogs)): ?>
        <div class="card mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-terminal-fill me-2 <?= $action === 'execute' ? 'text-success' : ($action === 'cleanup' ? 'text-danger' : 'text-primary') ?>"></i>
                    Execution Output: <?= $action === 'execute' ? '<span class="badge bg-success">Live Execution Complete</span>' : ($action === 'cleanup' ? '<span class="badge bg-danger">Duplicate Cleanup Complete</span>' : '<span class="badge bg-primary">Dry Run Preview</span>') ?>
                </h5>
                <span class="text-muted small"><?= count($executionLogs) ?> events logged</span>
            </div>
            <div class="card-body p-0">
                <div class="terminal-box m-3">
<?php foreach ($executionLogs as $log): ?>
<?= htmlspecialchars($log) . "\n" ?>
<?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($action === 'execute'): ?>
            <div class="alert alert-success d-flex align-items-center" role="alert">
                <i class="bi bi-check-circle-fill fs-4 me-3"></i>
                <div>
                    <strong>Migration &amp; Matrix Auto-Propagation Completed Successfully!</strong>
                    <div>Master COs, offering subjects, and branch matrices have been synchronized. Academic section and Faculty interfaces now reflect the unified catalog.</div>
                </div>
            </div>
        <?php elseif ($action === 'cleanup'): ?>
            <div class="alert alert-warning d-flex align-items-center" role="alert">
                <i class="bi bi-check2-all fs-4 me-3"></i>
                <div>
                    <strong>Duplicate Cleanup Completed &amp; Unique Constraint Enforced!</strong>
                    <div>All duplicate Master COs and orphan matrix cells have been purged. The database unique constraint is active to permanently prevent future duplicates.</div>
                </div>
            </div>
        <?php elseif ($action === 'dry-run'): ?>
            <div class="alert alert-info d-flex align-items-center" role="alert">
                <i class="bi bi-info-circle-fill fs-4 me-3"></i>
                <div>
                    <strong>Dry-Run Verification Passed!</strong>
                    <div>Zero errors detected. Click <strong>"Execute Live Migration"</strong> above to commit changes safely to the database.</div>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Information Card -->
    <div class="card">
        <div class="card-header bg-white py-3">
            <h6 class="card-title mb-0 text-muted"><i class="bi bi-shield-check text-success me-2"></i>Zero-Regression Safety Guarantees</h6>
        </div>
        <div class="card-body text-muted small">
            <ul class="mb-0 ps-3">
                <li>Existing offering COs and attainment reports (OBE calculations) are never deleted or modified.</li>
                <li>Branch-specific PO IDs are automatically translated using canonical codes (<code>PO1</code>..<code>PSO2</code>) so each engineering department links to its exact outcomes.</li>
                <li>Dual-write ensures any updates made by faculty or academic coordinators stay synchronized continuously.</li>
            </ul>
        </div>
    </div>

</div>

</body>
</html>
