<?php
// scripts/migrate_cos_dual_sync.php
// CLI migration script to harvest existing offering COs and matrices into Master records and backfill siblings

require_once __DIR__ . '/../services/CourseOutcomeSyncService.php';

echo "========================================================\n";
echo "Starting Safe CO & Articulation Matrix Dual-Sync Migration\n";
echo "========================================================\n\n";

$service = CourseOutcomeSyncService::getInstance();

// 1. Dry run first
echo "Running Dry-Run Verification...\n";
$dryRun = $service->migrateExistingLegacyData(true);

echo "Curriculum subjects to scan:      {$dryRun['curr_subs_scanned']}\n";
echo "Master COs to create:             {$dryRun['master_cos_created']}\n";
echo "Master mappings to create:        {$dryRun['master_mappings_created']}\n";
echo "Sibling offering COs to backfill: {$dryRun['sibling_cos_backfilled']}\n";
echo "Sibling mappings to backfill:     {$dryRun['sibling_mappings_backfilled']}\n";

if (!empty($dryRun['error'])) {
    echo "ERROR during dry-run: {$dryRun['error']}\n";
    exit(1);
}

echo "\nExecuting live migration...\n";
$liveRun = $service->migrateExistingLegacyData(false);

echo "--------------------------------------------------------\n";
echo "Migration Results:\n";
echo "--------------------------------------------------------\n";
echo "Curriculum subjects processed:    {$liveRun['curr_subs_scanned']}\n";
echo "Master COs successfully created:  {$liveRun['master_cos_created']}\n";
echo "Master mappings created:          {$liveRun['master_mappings_created']}\n";
echo "Sibling offering COs backfilled:  {$liveRun['sibling_cos_backfilled']}\n";
echo "Sibling mappings backfilled:      {$liveRun['sibling_mappings_backfilled']}\n";

if (!empty($liveRun['error'])) {
    echo "ERROR during live migration: {$liveRun['error']}\n";
    exit(1);
}

echo "\n========================================================\n";
echo "Migration Complete! All Master and Sibling records synchronized.\n";
echo "========================================================\n";
