<?php
declare(strict_types=1);

/**
 * Audit Logging Database Migration Runner
 * 
 * Usage:
 *   CLI: php migrations/run_audit_migration.php
 *   Web: navigate to https://<domain>/migrations/run_audit_migration.php (CLI preferred)
 */

if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "======================================================\n";
echo "   JNTUA CEA - Audit Logs Migration Runner\n";
echo "======================================================\n\n";

require_once __DIR__ . '/../dbcredentials.class.php';

try {
    $db = new \DBCredentials();
    $conn = $db->getConnection();

    if (!$conn || $conn->connect_errno) {
        throw new \Exception("Database connection failed: " . ($conn ? $conn->connect_error : "Unknown error"));
    }
    $currentDb = $conn->query("SELECT DATABASE()")->fetch_row()[0] ?? 'current';
    echo "[OK] Connected to database: " . $currentDb . "\n";

    $sqlFile = __DIR__ . '/20261005_audit_logs_system.sql';
    if (!file_exists($sqlFile)) {
        throw new \Exception("Migration SQL file not found at: $sqlFile");
    }

    $sqlContent = file_get_contents($sqlFile);
    if (empty($sqlContent)) {
        throw new \Exception("Migration SQL file is empty.");
    }

    // Split SQL into individual statements
    $queries = array_filter(
        array_map('trim', explode(';', $sqlContent)),
        fn($q) => !empty($q) && !preg_match('/^(--|\/\*)/', $q)
    );

    echo "[INFO] Found " . count($queries) . " statements to execute...\n";

    foreach ($queries as $index => $query) {
        if (empty($query)) continue;
        if (!$conn->query($query)) {
            throw new \Exception("Query #" . ($index + 1) . " failed: " . $conn->error . "\nSQL: $query");
        }
    }
    echo "[OK] All migration statements executed successfully.\n\n";

    // Verification
    echo "--- Verifying Migrated Artifacts ---\n";
    $tblCheck = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if ($tblCheck && $tblCheck->num_rows > 0) {
        echo "[PASS] Table 'audit_logs' verified.\n";
    } else {
        echo "[FAIL] Table 'audit_logs' not found.\n";
    }

    echo "\n======================================================\n";
    echo "   MIGRATION COMPLETED SUCCESSFULLY\n";
    echo "======================================================\n";

} catch (\Throwable $e) {
    echo "\n[ERROR] Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
