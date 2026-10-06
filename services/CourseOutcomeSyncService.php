<?php
// services/CourseOutcomeSyncService.php
// Centralized bidirectional synchronization between Master Curriculum COs/Matrix and Subject Offerings
// Enhanced to synchronize across all curriculum variants and parallel class sections sharing the same Subject Code and Regulation.

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../logs.class.php';

class CourseOutcomeSyncService extends DBCredentials
{
    private static ?CourseOutcomeSyncService $instance = null;

    public static function getInstance(): CourseOutcomeSyncService
    {
        if (self::$instance === null) {
            self::$instance = new CourseOutcomeSyncService();
        }
        return self::$instance;
    }

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Resolves all matching curriculum_subjects IDs sharing the same subcode and regulation (or explicit curr_sub_id).
     */
    public function getMatchingCurriculumSubjectIds(?int $currSubId, string $subcode, int $regId): array
    {
        $ids = [];
        if ($currSubId) {
            $ids[] = $currSubId;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT id FROM curriculum_subjects 
                WHERE (id = ? OR (subcode = ? AND reg_id = ?))
            ");
            $subcode = trim($subcode);
            $stmt->bind_param("isi", $currSubId, $subcode, $regId);
            $stmt->execute();
            $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            foreach ($rows as $r) {
                $id = (int)$r['id'];
                if (!in_array($id, $ids)) {
                    $ids[] = $id;
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog("CourseOutcomeSyncService::getMatchingCurriculumSubjectIds Error: " . $e->getMessage());
        }

        return $ids;
    }

    /**
     * Resolves all matching offering subjects sharing any of the curriculum subject IDs OR same subcode & regulation.
     */
    public function getMatchingOfferingSubjects(array $currSubIds, string $subcode, int $regId): array
    {
        $subjects = [];
        try {
            $subcode = trim($subcode);
            if (!empty($currSubIds)) {
                $ph = implode(',', array_fill(0, count($currSubIds), '?'));
                $types = str_repeat('i', count($currSubIds)) . 'si';
                $params = array_merge($currSubIds, [$subcode, $regId]);
                $sql = "
                    SELECT s.id, s.class_id, s.curr_sub_id, s.subcode, c.reg_id
                    FROM subjects s
                    JOIN classes c ON s.class_id = c.id
                    WHERE (s.curr_sub_id IN ($ph) OR (s.subcode = ? AND c.reg_id = ?))
                ";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param($types, ...$params);
            } else {
                $sql = "
                    SELECT s.id, s.class_id, s.curr_sub_id, s.subcode, c.reg_id
                    FROM subjects s
                    JOIN classes c ON s.class_id = c.id
                    WHERE s.subcode = ? AND c.reg_id = ?
                ";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("si", $subcode, $regId);
            }

            $stmt->execute();
            $subjects = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("CourseOutcomeSyncService::getMatchingOfferingSubjects Error: " . $e->getMessage());
        }

        return $subjects;
    }

    /**
     * Resolves a po_id to its canonical code ('PO1', 'PO2', ..., 'PSO1', etc.)
     */
    public function getPoCodeById(int $poId): ?string
    {
        $stmt = $this->conn->prepare("SELECT code FROM po_pso WHERE id = ?");
        $stmt->bind_param("i", $poId);
        $stmt->execute();
        $code = $stmt->get_result()->fetch_assoc()['code'] ?? null;
        $stmt->close();
        return $code ? trim($code) : null;
    }

    /**
     * Resolves a po_code ('PO1'..) to the specific po_id for a curriculum subject (curr_sub_id)
     */
    public function getPoIdForCurriculumSubject(int $currSubId, string $poCode): ?int
    {
        $stmt = $this->conn->prepare("
            SELECT cs.reg_id, cs.spec_id, r.regulation, sp.dept_id
            FROM curriculum_subjects cs
            JOIN regulations r ON cs.reg_id = r.id
            LEFT JOIN specialization sp ON cs.spec_id = sp.id
            WHERE cs.id = ?
        ");
        $stmt->bind_param("i", $currSubId);
        $stmt->execute();
        $cs = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cs) return null;

        return $this->resolvePoIdByCodeAndContext($poCode, (int)$cs['reg_id'], (int)($cs['spec_id'] ?? 0), $cs['regulation'] ?? '', (int)($cs['dept_id'] ?? 0));
    }

    /**
     * Resolves a po_code ('PO1'..) to the specific po_id for an offering subject (sub_id)
     */
    public function getPoIdForOfferingSubject(int $subId, string $poCode): ?int
    {
        $stmt = $this->conn->prepare("
            SELECT c.reg_id, c.spec_id, r.regulation, c.acad_year, sp.dept_id
            FROM subjects s
            JOIN classes c ON s.class_id = c.id
            JOIN regulations r ON c.reg_id = r.id
            LEFT JOIN specialization sp ON c.spec_id = sp.id
            WHERE s.id = ?
        ");
        $stmt->bind_param("i", $subId);
        $stmt->execute();
        $sub = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$sub) return null;

        return $this->resolvePoIdByCodeAndContext($poCode, (int)$sub['reg_id'], (int)($sub['spec_id'] ?? 0), $sub['regulation'] ?? '', (int)($sub['dept_id'] ?? 0), $sub['acad_year'] ?? '');
    }

    /**
     * Robust PO resolution by code, matching specialization and regulation
     */
    public function resolvePoIdByCodeAndContext(string $poCode, int $regId, int $specId = 0, string $regulation = '', int $deptId = 0, string $acadYear = ''): ?int
    {
        $poCode = trim($poCode);
        if ($poCode === '') return null;

        // Ensure regulation string is populated if we have regId
        if (empty($regulation) && !empty($regId)) {
            $regStmt = $this->conn->prepare("SELECT regulation FROM regulations WHERE id = ?");
            if ($regStmt) {
                $regStmt->bind_param("i", $regId);
                $regStmt->execute();
                $regulation = $regStmt->get_result()->fetch_assoc()['regulation'] ?? '';
                $regStmt->close();
            }
        }

        // Auto-resolve dept_id from specialization if not provided
        if ($deptId == 0 && $specId > 0) {
            $spStmt = $this->conn->prepare("SELECT dept_id FROM specialization WHERE id = ?");
            if ($spStmt) {
                $spStmt->bind_param("i", $specId);
                $spStmt->execute();
                $deptId = (int)($spStmt->get_result()->fetch_assoc()['dept_id'] ?? 0);
                $spStmt->close();
            }
        }

        // Determine candidate specialization IDs (exact spec_id first, then parent dept_id)
        $candidateSpecs = [];
        if ($specId > 0) {
            $candidateSpecs[] = $specId;
        }
        if ($deptId > 0 && !in_array($deptId, $candidateSpecs)) {
            $candidateSpecs[] = $deptId;
        }

        // 1. Try matching with acad_year across candidate specializations (with regulation constraint)
        if (!empty($acadYear)) {
            foreach ($candidateSpecs as $candSpec) {
                $s1 = $this->conn->prepare("
                    SELECT id FROM po_pso 
                    WHERE code = ? AND acad_year = ? AND specid = ? 
                      AND ((reg_id = ? AND reg_id != 0) OR (regulation = ? AND regulation != ''))
                    ORDER BY id ASC LIMIT 1
                ");
                if ($s1) {
                    $s1->bind_param("ssiis", $poCode, $acadYear, $candSpec, $regId, $regulation);
                    $s1->execute();
                    $id = $s1->get_result()->fetch_assoc()['id'] ?? null;
                    $s1->close();
                    if ($id) return (int)$id;
                }
            }
        }

        // 2. Try matching without acad_year across candidate specializations (with regulation constraint)
        foreach ($candidateSpecs as $candSpec) {
            $s2 = $this->conn->prepare("
                SELECT id FROM po_pso 
                WHERE code = ? AND specid = ? 
                  AND ((reg_id = ? AND reg_id != 0) OR (regulation = ? AND regulation != ''))
                ORDER BY id ASC LIMIT 1
            ");
            if ($s2) {
                $s2->bind_param("siis", $poCode, $candSpec, $regId, $regulation);
                $s2->execute();
                $id = $s2->get_result()->fetch_assoc()['id'] ?? null;
                $s2->close();
                if ($id) return (int)$id;
            }
        }

        // Note: We deliberately do NOT fall back to arbitrary other specializations or regulations.
        // If a program/department doesn't define this PO/PSO (e.g. PSO3), it must return null
        // rather than borrowing another branch's PO/PSO, which violates academic mapping rules.
        return null;
    }

    /**
     * Extracts logical matrix [co_number => [po_code => weightage]] from a curriculum subject
     */
    public function extractLogicalMatrixFromCurriculumSubject(int $currSubId): array
    {
        $matrix = [];
        $stmt = $this->conn->prepare("
            SELECT co.co_number, p.code as po_code, m.weightage
            FROM course_outcomes co
            JOIN co_po_mapping m ON m.co_id = co.id
            JOIN po_pso p ON m.po_id = p.id
            WHERE co.curr_sub_id = ? AND co.sub_id IS NULL AND m.weightage IN (1, 2, 3)
            ORDER BY co.co_number ASC, p.id ASC
        ");
        $stmt->bind_param("i", $currSubId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $r) {
            $coNum = (int)$r['co_number'];
            $poCode = trim($r['po_code']);
            $w = (int)$r['weightage'];
            if (!isset($matrix[$coNum])) $matrix[$coNum] = [];
            $matrix[$coNum][$poCode] = $w;
        }
        return $matrix;
    }

    /**
     * Extracts logical matrix [co_number => [po_code => weightage]] from an offering subject
     */
    public function extractLogicalMatrixFromOfferingSubject(int $subId): array
    {
        $matrix = [];
        $stmt = $this->conn->prepare("
            SELECT co.co_number, p.code as po_code, m.weightage
            FROM course_outcomes co
            JOIN co_po_mapping m ON m.co_id = co.id
            JOIN po_pso p ON m.po_id = p.id
            WHERE co.sub_id = ? AND m.weightage IN (1, 2, 3)
            ORDER BY co.co_number ASC, p.id ASC
        ");
        $stmt->bind_param("i", $subId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $r) {
            $coNum = (int)$r['co_number'];
            $poCode = trim($r['po_code']);
            $w = (int)$r['weightage'];
            if (!isset($matrix[$coNum])) $matrix[$coNum] = [];
            $matrix[$coNum][$poCode] = $w;
        }
        return $matrix;
    }

    /**
     * Searches across ALL curriculum subjects and class offerings sharing (subcode, reg_id)
     * to find any existing defined logical matrix.
     */
    public function findLogicalMatrixBySubjectCode(string $subcode, int $regId): array
    {
        $subcode = trim($subcode);
        if (empty($subcode) || empty($regId)) return [];

        // 1. Check all curriculum subjects for this subcode and reg_id
        $currIds = $this->getMatchingCurriculumSubjectIds(null, $subcode, $regId);
        foreach ($currIds as $csId) {
            $mat = $this->extractLogicalMatrixFromCurriculumSubject($csId);
            if (!empty($mat)) {
                return $mat;
            }
        }

        // 2. Check all class offerings for this subcode and reg_id
        $offerings = $this->getMatchingOfferingSubjects($currIds, $subcode, $regId);
        foreach ($offerings as $off) {
            $mat = $this->extractLogicalMatrixFromOfferingSubject((int)$off['id']);
            if (!empty($mat)) {
                return $mat;
            }
        }

        return [];
    }

    /**
     * Retrieves the best available CO definitions [co_number => ['desc' => ..., 'bloom' => ..., 'thresh' => ...]]
     * across all curriculum subjects and offerings for a given subcode and reg_id.
     */
    public function getSourceCoDefinitions(string $subcode, int $regId): array
    {
        $defs = [];
        $stmt = $this->conn->prepare("
            SELECT co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent
            FROM course_outcomes co
            JOIN curriculum_subjects cs ON co.curr_sub_id = cs.id
            WHERE cs.subcode = ? AND cs.reg_id = ? AND co.sub_id IS NULL
            ORDER BY co.co_number ASC
        ");
        $stmt->bind_param("si", $subcode, $regId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($rows as $r) {
            $num = (int)$r['co_number'];
            if (!isset($defs[$num])) {
                $defs[$num] = [
                    'desc' => $r['co_description'],
                    'bloom' => $r['bloom_level'] ?? 'L3-Apply',
                    'thresh' => (float)($r['target_threshold_percent'] ?? 60.0)
                ];
            }
        }

        if (empty($defs)) {
            // Fallback: check offerings
            $stmt2 = $this->conn->prepare("
                SELECT co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent
                FROM course_outcomes co
                JOIN subjects s ON co.sub_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE s.subcode = ? AND c.reg_id = ?
                ORDER BY co.co_number ASC
            ");
            $stmt2->bind_param("si", $subcode, $regId);
            $stmt2->execute();
            $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt2->close();
            foreach ($rows2 as $r) {
                $num = (int)$r['co_number'];
                if (!isset($defs[$num])) {
                    $defs[$num] = [
                        'desc' => $r['co_description'],
                        'bloom' => $r['bloom_level'] ?? 'L3-Apply',
                        'thresh' => (float)($r['target_threshold_percent'] ?? 60.0)
                    ];
                }
            }
        }

        return $defs;
    }

    public function ensureMasterCOsExist(int $currSubId, array $sourceDefs): void
    {
        if (empty($sourceDefs)) return;

        $chk = $this->conn->prepare("SELECT id FROM course_outcomes WHERE curr_sub_id = ? AND co_number = ? AND sub_id IS NULL ORDER BY id ASC LIMIT 1");
        $ins = $this->conn->prepare("
            INSERT INTO course_outcomes (curr_sub_id, sub_id, co_number, co_description, bloom_level, target_threshold_percent)
            VALUES (?, NULL, ?, ?, ?, ?)
        ");
        $upd = $this->conn->prepare("
            UPDATE course_outcomes 
            SET co_description = ?, bloom_level = ?, target_threshold_percent = ?
            WHERE id = ?
        ");

        foreach ($sourceDefs as $num => $d) {
            $chk->bind_param("ii", $currSubId, $num);
            $chk->execute();
            $existingId = $chk->get_result()->fetch_assoc()['id'] ?? null;

            if ($existingId) {
                $upd->bind_param("ssdi", $d['desc'], $d['bloom'], $d['thresh'], $existingId);
                $upd->execute();
            } else {
                $ins->bind_param("iissd", $currSubId, $num, $d['desc'], $d['bloom'], $d['thresh']);
                $ins->execute();
            }
        }
        $chk->close();
        $ins->close();
        $upd->close();
    }

    public function ensureOfferingCOsExist(int $subId, ?int $currSubId, array $sourceDefs): void
    {
        if (empty($sourceDefs)) return;

        $chk = $this->conn->prepare("SELECT id FROM course_outcomes WHERE sub_id = ? AND co_number = ? ORDER BY id ASC LIMIT 1");
        $ins = $this->conn->prepare("
            INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $upd = $this->conn->prepare("
            UPDATE course_outcomes 
            SET curr_sub_id = ?, co_description = ?, bloom_level = ?, target_threshold_percent = ?
            WHERE id = ?
        ");

        foreach ($sourceDefs as $num => $d) {
            $chk->bind_param("ii", $subId, $num);
            $chk->execute();
            $existingId = $chk->get_result()->fetch_assoc()['id'] ?? null;

            if ($existingId) {
                $upd->bind_param("issdi", $currSubId, $d['desc'], $d['bloom'], $d['thresh'], $existingId);
                $upd->execute();
            } else {
                $ins->bind_param("iiissd", $subId, $currSubId, $num, $d['desc'], $d['bloom'], $d['thresh']);
                $ins->execute();
            }
        }
        $chk->close();
        $ins->close();
        $upd->close();
    }

    public function getMasterCoMap(int $currSubId): array
    {
        $map = [];
        $stmt = $this->conn->prepare("
            SELECT MIN(id) as id, co_number 
            FROM course_outcomes 
            WHERE curr_sub_id = ? AND sub_id IS NULL 
            GROUP BY co_number
        ");
        $stmt->bind_param("i", $currSubId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $r) {
            $map[(int)$r['co_number']] = (int)$r['id'];
        }
        return $map;
    }

    public function getOfferingCoMap(int $subId): array
    {
        $map = [];
        $stmt = $this->conn->prepare("
            SELECT MIN(id) as id, co_number 
            FROM course_outcomes 
            WHERE sub_id = ? 
            GROUP BY co_number
        ");
        $stmt->bind_param("i", $subId);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        foreach ($rows as $r) {
            $map[(int)$r['co_number']] = (int)$r['id'];
        }
        return $map;
    }

    /**
     * Propagates a logical matrix [co_number => [po_code => weightage]]
     * to ALL curriculum subjects (curr_sub_id) and ALL class offerings (sub_id)
     * sharing the same subcode and reg_id.
     */
    public function propagateLogicalMatrixBySubjectCode(string $subcode, int $regId, array $logicalMatrix): array
    {
        $res = [
            'status' => 0,
            'masters_updated' => 0,
            'offerings_updated' => 0,
            'cells_written' => 0
        ];

        if (empty($subcode) || empty($regId) || empty($logicalMatrix)) {
            $res['error'] = 'Invalid parameters for matrix propagation';
            return $res;
        }

        try {
            $matchingCurrSubIds = $this->getMatchingCurriculumSubjectIds(null, $subcode, $regId);
            $sourceCoDefs = $this->getSourceCoDefinitions($subcode, $regId);

            $insMap = $this->conn->prepare("
                INSERT INTO co_po_mapping (co_id, po_id, weightage)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
            ");

            // 1. Propagate to ALL matching curriculum subjects
            foreach ($matchingCurrSubIds as $csId) {
                $this->ensureMasterCOsExist($csId, $sourceCoDefs);
                $coMap = $this->getMasterCoMap($csId);

                $csCells = 0;
                foreach ($logicalMatrix as $coNumber => $poMappings) {
                    if (!isset($coMap[$coNumber])) continue;
                    $targetCoId = $coMap[$coNumber];

                    foreach ($poMappings as $poCode => $weightage) {
                        $targetPoId = $this->getPoIdForCurriculumSubject($csId, $poCode);
                        if ($targetPoId && in_array((int)$weightage, [1, 2, 3])) {
                            $w = (int)$weightage;
                            $insMap->bind_param("iii", $targetCoId, $targetPoId, $w);
                            if ($insMap->execute()) {
                                $res['cells_written']++;
                                $csCells++;
                            }
                        }
                    }
                }
                if ($csCells > 0) $res['masters_updated']++;
            }

            // 2. Propagate to ALL matching class offerings
            $allOfferings = $this->getMatchingOfferingSubjects($matchingCurrSubIds, $subcode, $regId);
            foreach ($allOfferings as $off) {
                $offId = (int)$off['id'];
                $offCurrId = !empty($off['curr_sub_id']) ? (int)$off['curr_sub_id'] : ($matchingCurrSubIds[0] ?? null);

                $this->ensureOfferingCOsExist($offId, $offCurrId, $sourceCoDefs);
                $coMap = $this->getOfferingCoMap($offId);

                $offCells = 0;
                foreach ($logicalMatrix as $coNumber => $poMappings) {
                    if (!isset($coMap[$coNumber])) continue;
                    $targetCoId = $coMap[$coNumber];

                    foreach ($poMappings as $poCode => $weightage) {
                        $targetPoId = $this->getPoIdForOfferingSubject($offId, $poCode);
                        if ($targetPoId && in_array((int)$weightage, [1, 2, 3])) {
                            $w = (int)$weightage;
                            $insMap->bind_param("iii", $targetCoId, $targetPoId, $w);
                            if ($insMap->execute()) {
                                $res['cells_written']++;
                                $offCells++;
                            }
                        }
                    }
                }
                if ($offCells > 0) $res['offerings_updated']++;
            }

            $insMap->close();
            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("propagateLogicalMatrixBySubjectCode Error: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Dual-write when Faculty adds/updates a CO for an offering subject.
     * Updates/Inserts:
     *  1. Current offering CO (sub_id = $subId)
     *  2. Master COs across ALL matching curriculum_subjects (sharing subcode & reg_id)
     *  3. ALL sibling offering subjects (across any class sharing subcode & reg_id).
     */
    public function syncOfferingCOToMasterAndSiblings(int $subId, int $coNumber, string $description, string $bloomLevel = 'L3-Apply', float $targetThreshold = 60.0): array
    {
        $res = ['status' => 0, 'offering_saved' => false, 'master_saved' => false, 'siblings_updated' => 0];

        try {
            // 1. Get subject details: subcode, curr_sub_id, and class reg_id
            $stmt = $this->conn->prepare("
                SELECT s.subcode, s.curr_sub_id, c.reg_id
                FROM subjects s
                JOIN classes c ON s.class_id = c.id
                WHERE s.id = ?
            ");
            if (!$stmt) throw new Exception("Prepare failed: " . $this->conn->error);
            $stmt->bind_param("i", $subId);
            $stmt->execute();
            $subData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$subData) {
                throw new Exception("Subject ID $subId not found");
            }

            $subcode = trim($subData['subcode']);
            $regId = (int)$subData['reg_id'];
            $currSubId = !empty($subData['curr_sub_id']) ? (int)$subData['curr_sub_id'] : null;

            // Find all matching curriculum subjects
            $matchingCurrSubIds = $this->getMatchingCurriculumSubjectIds($currSubId, $subcode, $regId);

            // If subject curr_sub_id was NULL but matching curriculum subjects exist, auto-bind the first match
            if (!$currSubId && !empty($matchingCurrSubIds)) {
                $currSubId = $matchingCurrSubIds[0];
                $updCurr = $this->conn->prepare("UPDATE subjects SET curr_sub_id = ? WHERE id = ?");
                $updCurr->bind_param("ii", $currSubId, $subId);
                $updCurr->execute();
                $updCurr->close();
            }

            // 2. Save offering CO for current subject
            $offStmt = $this->conn->prepare("
                INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    curr_sub_id = VALUES(curr_sub_id),
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");
            if (!$offStmt) throw new Exception("Prepare offStmt failed: " . $this->conn->error);
            $offStmt->bind_param("iiissd", $subId, $currSubId, $coNumber, $description, $bloomLevel, $targetThreshold);
            if ($offStmt->execute()) {
                $res['offering_saved'] = true;
            }
            $offStmt->close();

            // 3. Upsert Master COs across ALL matching curriculum_subjects
            if (!empty($matchingCurrSubIds)) {
                $insMaster = $this->conn->prepare("
                    INSERT INTO course_outcomes (curr_sub_id, sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                    VALUES (?, NULL, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        co_description = VALUES(co_description),
                        bloom_level = VALUES(bloom_level),
                        target_threshold_percent = VALUES(target_threshold_percent)
                ");

                foreach ($matchingCurrSubIds as $csId) {
                    // Check if exists
                    $chkMaster = $this->conn->prepare("SELECT id FROM course_outcomes WHERE curr_sub_id = ? AND co_number = ? AND sub_id IS NULL LIMIT 1");
                    $chkMaster->bind_param("ii", $csId, $coNumber);
                    $chkMaster->execute();
                    $masterCoId = $chkMaster->get_result()->fetch_assoc()['id'] ?? null;
                    $chkMaster->close();

                    if ($masterCoId) {
                        $updMaster = $this->conn->prepare("UPDATE course_outcomes SET co_description = ?, bloom_level = ?, target_threshold_percent = ? WHERE id = ?");
                        $updMaster->bind_param("ssdi", $description, $bloomLevel, $targetThreshold, $masterCoId);
                        $updMaster->execute();
                        $updMaster->close();
                    } else {
                        $insMaster->bind_param("iissd", $csId, $coNumber, $description, $bloomLevel, $targetThreshold);
                        $insMaster->execute();
                    }
                    $res['master_saved'] = true;
                }
                $insMaster->close();
            }

            // 4. Cascade to ALL sibling offering subjects (sharing any matching curr_sub_id OR subcode + reg_id)
            $allOfferings = $this->getMatchingOfferingSubjects($matchingCurrSubIds, $subcode, $regId);

            if (!empty($allOfferings)) {
                $insSib = $this->conn->prepare("
                    INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        curr_sub_id = VALUES(curr_sub_id),
                        co_description = VALUES(co_description),
                        bloom_level = VALUES(bloom_level),
                        target_threshold_percent = VALUES(target_threshold_percent)
                ");

                foreach ($allOfferings as $offering) {
                    $offId = (int)$offering['id'];
                    if ($offId === $subId) continue; // Current subject already saved

                    $offCurrSubId = !empty($offering['curr_sub_id']) ? (int)$offering['curr_sub_id'] : ($matchingCurrSubIds[0] ?? null);

                    // Auto-link curr_sub_id on sibling if NULL
                    if (empty($offering['curr_sub_id']) && $offCurrSubId) {
                        $updSibCurr = $this->conn->prepare("UPDATE subjects SET curr_sub_id = ? WHERE id = ?");
                        $updSibCurr->bind_param("ii", $offCurrSubId, $offId);
                        $updSibCurr->execute();
                        $updSibCurr->close();
                    }

                    $insSib->bind_param("iiissd", $offId, $offCurrSubId, $coNumber, $description, $bloomLevel, $targetThreshold);
                    if ($insSib->execute()) {
                        $res['siblings_updated']++;
                    }
                }
                $insSib->close();
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CourseOutcomeSyncService::syncOfferingCOToMasterAndSiblings Error: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Dual-write when Faculty adds/updates an Articulation Matrix mapping (CO -> PO).
     * Synchronizes to:
     *  1. Current offering co_po_mapping
     *  2. Master co_po_mapping across all matching curriculum subjects
     *  3. All sibling offerings sharing the same subcode and regulation
     */
    public function syncOfferingMappingToMasterAndSiblings(int $subId, int $coId, int $poId, int $weightage): array
    {
        $res = ['status' => 0, 'offering_saved' => false, 'master_saved' => false, 'siblings_updated' => 0];

        try {
            if (!in_array($weightage, [1, 2, 3])) {
                throw new Exception("Invalid weightage ($weightage)");
            }

            // 1. Get co_number, subcode, curr_sub_id, and class reg_id
            $coStmt = $this->conn->prepare("
                SELECT co.co_number, s.subcode, s.curr_sub_id, c.reg_id
                FROM course_outcomes co
                JOIN subjects s ON co.sub_id = s.id
                JOIN classes c ON s.class_id = c.id
                WHERE co.id = ? AND s.id = ?
            ");
            $coStmt->bind_param("ii", $coId, $subId);
            $coStmt->execute();
            $coData = $coStmt->get_result()->fetch_assoc();
            $coStmt->close();

            if (!$coData) {
                throw new Exception("CO ID $coId not found for subject $subId");
            }

            $coNumber = (int)$coData['co_number'];
            $subcode = trim($coData['subcode']);
            $regId = (int)$coData['reg_id'];

            // 2. Save offering mapping for current subject directly
            $mapStmt = $this->conn->prepare("
                INSERT INTO co_po_mapping (co_id, po_id, weightage)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
            ");
            $mapStmt->bind_param("iii", $coId, $poId, $weightage);
            if ($mapStmt->execute()) {
                $res['offering_saved'] = true;
            }
            $mapStmt->close();

            // 3. Resolve canonical po_code and propagate across all curr_sub_ids and sibling sub_ids
            $poCode = $this->getPoCodeById($poId);
            if ($poCode && $subcode && $regId) {
                $logicalCell = [
                    $coNumber => [
                        $poCode => $weightage
                    ]
                ];
                $propRes = $this->propagateLogicalMatrixBySubjectCode($subcode, $regId, $logicalCell);
                $res['master_saved'] = ($propRes['masters_updated'] > 0);
                $res['siblings_updated'] = max(0, $propRes['offerings_updated'] - 1);
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CourseOutcomeSyncService::syncOfferingMappingToMasterAndSiblings Error: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Dual-write when Academic Section adds/updates Master CO in curriculum_subjects.
     * Cascades across all matching curriculum subject variants and all active offerings.
     */
    public function syncMasterCOToOfferings(int $currSubId, int $coNumber, string $description, string $bloomLevel = 'L3-Apply', float $targetThreshold = 60.0): array
    {
        $res = ['status' => 0, 'master_saved' => false, 'offerings_updated' => 0];

        try {
            // Get subcode and reg_id for this curriculum subject
            $stmt = $this->conn->prepare("SELECT subcode, reg_id FROM curriculum_subjects WHERE id = ?");
            $stmt->bind_param("i", $currSubId);
            $stmt->execute();
            $csData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$csData) throw new Exception("Curriculum Subject ID $currSubId not found");

            $subcode = trim($csData['subcode']);
            $regId = (int)$csData['reg_id'];

            // 1. Find all matching curriculum subjects
            $matchingCurrSubIds = $this->getMatchingCurriculumSubjectIds($currSubId, $subcode, $regId);

            // Upsert Master CO for all matching curriculum subjects
            foreach ($matchingCurrSubIds as $csId) {
                $chk = $this->conn->prepare("SELECT id FROM course_outcomes WHERE curr_sub_id = ? AND co_number = ? AND sub_id IS NULL LIMIT 1");
                $chk->bind_param("ii", $csId, $coNumber);
                $chk->execute();
                $masterCoId = $chk->get_result()->fetch_assoc()['id'] ?? null;
                $chk->close();

                if ($masterCoId) {
                    $upd = $this->conn->prepare("UPDATE course_outcomes SET co_description = ?, bloom_level = ?, target_threshold_percent = ? WHERE id = ?");
                    $upd->bind_param("ssdi", $description, $bloomLevel, $targetThreshold, $masterCoId);
                    $upd->execute();
                    $upd->close();
                } else {
                    $ins = $this->conn->prepare("
                        INSERT INTO course_outcomes (curr_sub_id, sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                        VALUES (?, NULL, ?, ?, ?, ?)
                    ");
                    $ins->bind_param("iissd", $csId, $coNumber, $description, $bloomLevel, $targetThreshold);
                    $ins->execute();
                    $ins->close();
                }
            }
            $res['master_saved'] = true;

            // 2. Cascade to all matching offering subjects across all classes
            $allOfferings = $this->getMatchingOfferingSubjects($matchingCurrSubIds, $subcode, $regId);

            if (!empty($allOfferings)) {
                $insOff = $this->conn->prepare("
                    INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                    VALUES (?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        curr_sub_id = VALUES(curr_sub_id),
                        co_description = VALUES(co_description),
                        bloom_level = VALUES(bloom_level),
                        target_threshold_percent = VALUES(target_threshold_percent)
                ");
                foreach ($allOfferings as $off) {
                    $offSubId = (int)$off['id'];
                    $offCurrId = !empty($off['curr_sub_id']) ? (int)$off['curr_sub_id'] : $currSubId;

                    $insOff->bind_param("iiissd", $offSubId, $offCurrId, $coNumber, $description, $bloomLevel, $targetThreshold);
                    if ($insOff->execute()) {
                        $res['offerings_updated']++;
                    }
                }
                $insOff->close();
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CourseOutcomeSyncService::syncMasterCOToOfferings Error: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Dual-write when Academic Section saves Master Articulation Matrix.
     */
    public function syncMasterMatrixToOfferings(int $currSubId, array $submittedMappings): array
    {
        $res = ['status' => 0, 'master_saved' => 0, 'offerings_updated' => 0];

        try {
            // Get subcode and reg_id
            $stmt = $this->conn->prepare("SELECT subcode, reg_id FROM curriculum_subjects WHERE id = ?");
            $stmt->bind_param("i", $currSubId);
            $stmt->execute();
            $csData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$csData) throw new Exception("Curriculum Subject ID $currSubId not found");

            $subcode = trim($csData['subcode']);
            $regId = (int)$csData['reg_id'];

            // Get Master COs for the source curr_sub_id
            $stmt = $this->conn->prepare("
                SELECT id, co_number 
                FROM course_outcomes 
                WHERE curr_sub_id = ? AND sub_id IS NULL
            ");
            $stmt->bind_param("i", $currSubId);
            $stmt->execute();
            $masterCos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            if (empty($masterCos)) {
                throw new Exception("No Master COs found for curriculum subject $currSubId. Please define COs first.");
            }

            $coMap = []; // co_id => co_number
            foreach ($masterCos as $mc) {
                $coMap[(int)$mc['id']] = (int)$mc['co_number'];
            }

            // Convert submitted mappings to logical matrix [co_number => [po_code => weightage]]
            $logicalMatrix = [];
            foreach ($submittedMappings as $coId => $poMappings) {
                $coId = (int)$coId;
                if (!isset($coMap[$coId])) continue;
                $coNumber = $coMap[$coId];

                foreach ($poMappings as $poId => $weightRaw) {
                    $poId = (int)$poId;
                    if ($weightRaw === '' || $weightRaw === null) continue;
                    $weight = (int)$weightRaw;
                    if (!in_array($weight, [1, 2, 3])) continue;

                    $poCode = $this->getPoCodeById($poId);
                    if ($poCode) {
                        if (!isset($logicalMatrix[$coNumber])) $logicalMatrix[$coNumber] = [];
                        $logicalMatrix[$coNumber][$poCode] = $weight;
                    }
                }
            }

            if (!empty($logicalMatrix)) {
                $propRes = $this->propagateLogicalMatrixBySubjectCode($subcode, $regId, $logicalMatrix);
                $res['master_saved'] = $propRes['masters_updated'];
                $res['offerings_updated'] = $propRes['offerings_updated'];
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CourseOutcomeSyncService::syncMasterMatrixToOfferings Error: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Auto-provision COs & Matrix when HOD creates a subject offering from curriculum_subjects.
     */
    public function provisionNewOfferingCOsAndMatrix(int $newSubId, int $currSubId): array
    {
        $res = ['status' => 0, 'cos_copied' => 0, 'mappings_copied' => 0];

        try {
            // Get subcode and reg_id for currSubId
            $stmt = $this->conn->prepare("SELECT subcode, reg_id FROM curriculum_subjects WHERE id = ?");
            $stmt->bind_param("i", $currSubId);
            $stmt->execute();
            $csData = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $subcode = !empty($csData['subcode']) ? trim($csData['subcode']) : '';
            $regId = !empty($csData['reg_id']) ? (int)$csData['reg_id'] : 0;
            $matchingCurrSubIds = $this->getMatchingCurriculumSubjectIds($currSubId, $subcode, $regId);

            // 1. Fetch Master COs from currSubId or any matching curriculum subject
            $ph = implode(',', array_fill(0, count($matchingCurrSubIds), '?'));
            $types = str_repeat('i', count($matchingCurrSubIds));
            $stmt = $this->conn->prepare("
                SELECT id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent
                FROM course_outcomes
                WHERE curr_sub_id IN ($ph) AND sub_id IS NULL
                ORDER BY co_number ASC, (curr_sub_id = $currSubId) DESC
            ");
            $stmt->bind_param($types, ...$matchingCurrSubIds);
            $stmt->execute();
            $rawMasterCos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $masterCos = [];
            foreach ($rawMasterCos as $rc) {
                $num = (int)$rc['co_number'];
                if (!isset($masterCos[$num])) {
                    $masterCos[$num] = $rc;
                }
            }

            // Fallback: If no Master COs, check if any sibling offering has COs
            if (empty($masterCos) && $subcode) {
                $sibCosStmt = $this->conn->prepare("
                    SELECT co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent
                    FROM course_outcomes co
                    JOIN subjects s ON co.sub_id = s.id
                    JOIN classes c ON s.class_id = c.id
                    WHERE (s.curr_sub_id IN ($ph) OR (s.subcode = ? AND c.reg_id = ?))
                      AND s.id != ?
                    ORDER BY co.co_number ASC
                ");
                $sibTypes = $types . 'sii';
                $sibParams = array_merge($matchingCurrSubIds, [$subcode, $regId, $newSubId]);
                $sibCosStmt->bind_param($sibTypes, ...$sibParams);
                $sibCosStmt->execute();
                $harvested = $sibCosStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $sibCosStmt->close();

                if (!empty($harvested)) {
                    foreach ($harvested as $h) {
                        $num = (int)$h['co_number'];
                        if (!isset($masterCos[$num])) {
                            $masterCos[$num] = $h;
                            // Create master CO as well
                            $this->syncMasterCOToOfferings($currSubId, $num, $h['co_description'], $h['bloom_level'], (float)$h['target_threshold_percent']);
                        }
                    }
                }
            }

            if (empty($masterCos)) {
                $res['status'] = 1;
                return $res;
            }

            // 2. Insert offering COs for newSubId
            $insCo = $this->conn->prepare("
                INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");

            $offeringCoMap = [];
            $masterToOfferingId = [];

            foreach ($masterCos as $num => $mc) {
                $desc = $mc['co_description'];
                $bloom = $mc['bloom_level'];
                $target = (float)$mc['target_threshold_percent'];

                $insCo->bind_param("iiissd", $newSubId, $currSubId, $num, $desc, $bloom, $target);
                if ($insCo->execute()) {
                    $offCoId = $this->conn->insert_id;
                    if (!$offCoId) {
                        $chkId = $this->conn->prepare("SELECT id FROM course_outcomes WHERE sub_id = ? AND co_number = ? LIMIT 1");
                        $chkId->bind_param("ii", $newSubId, $num);
                        $chkId->execute();
                        $offCoId = $chkId->get_result()->fetch_assoc()['id'] ?? null;
                        $chkId->close();
                    }
                    if ($offCoId) {
                        if (!empty($mc['id'])) {
                            $masterToOfferingId[(int)$mc['id']] = (int)$offCoId;
                        }
                        $offeringCoMap[$num] = (int)$offCoId;
                        $res['cos_copied']++;
                    }
                }
            }
            $insCo->close();

            // 3. Replicate Articulation Matrix via canonical po_code
            $logicalMatrix = $this->findLogicalMatrixBySubjectCode($subcode, $regId);
            if (!empty($logicalMatrix) && !empty($offeringCoMap)) {
                $insMap = $this->conn->prepare("
                    INSERT INTO co_po_mapping (co_id, po_id, weightage)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE weightage = VALUES(weightage)
                ");
                foreach ($logicalMatrix as $coNumber => $poMappings) {
                    if (!isset($offeringCoMap[$coNumber])) continue;
                    $targetCoId = $offeringCoMap[$coNumber];

                    foreach ($poMappings as $poCode => $weight) {
                        $targetPoId = $this->getPoIdForOfferingSubject($newSubId, $poCode);
                        if ($targetPoId && in_array((int)$weight, [1, 2, 3])) {
                            $w = (int)$weight;
                            $insMap->bind_param("iii", $targetCoId, $targetPoId, $w);
                            if ($insMap->execute()) {
                                $res['mappings_copied']++;
                            }
                        }
                    }
                }
                $insMap->close();
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CourseOutcomeSyncService::provisionNewOfferingCOsAndMatrix Error: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Migration & backfill helper: Unifies by (subcode, reg_id).
     */
    public function migrateExistingLegacyData(bool $dryRun = false): array
    {
        $report = [
            'curr_subs_scanned' => 0,
            'master_cos_created' => 0,
            'master_mappings_created' => 0,
            'sibling_cos_backfilled' => 0,
            'sibling_mappings_backfilled' => 0,
            'details' => []
        ];

        try {
            // Find distinct (subcode, reg_id) among offerings with COs
            $sql = "
                SELECT DISTINCT s.subcode, c.reg_id, cs.id as curr_sub_id, cs.sub_fullname
                FROM course_outcomes co
                JOIN subjects s ON co.sub_id = s.id
                JOIN classes c ON s.class_id = c.id
                LEFT JOIN curriculum_subjects cs ON s.curr_sub_id = cs.id
                WHERE s.subcode IS NOT NULL AND s.subcode != ''
                ORDER BY c.reg_id ASC, s.subcode ASC
            ";
            $res = $this->conn->query($sql);
            $courses = $res->fetch_all(MYSQLI_ASSOC);

            // Group by subcode + reg_id
            $uniqueCourses = [];
            foreach ($courses as $c) {
                $key = strtoupper(trim($c['subcode'])) . '##' . (int)$c['reg_id'];
                if (!isset($uniqueCourses[$key])) {
                    $uniqueCourses[$key] = $c;
                }
            }

            foreach ($uniqueCourses as $course) {
                $subcode = trim($course['subcode']);
                $regId = (int)$course['reg_id'];
                $currSubId = !empty($course['curr_sub_id']) ? (int)$course['curr_sub_id'] : null;
                $report['curr_subs_scanned']++;

                // 1. Find all matching curriculum subjects
                $matchingCurrSubIds = $this->getMatchingCurriculumSubjectIds($currSubId, $subcode, $regId);

                // 2. Fetch all offering COs for this subcode & reg_id
                $offStmt = $this->conn->prepare("
                    SELECT co.id, co.sub_id, co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent
                    FROM course_outcomes co
                    JOIN subjects s ON co.sub_id = s.id
                    JOIN classes c ON s.class_id = c.id
                    WHERE s.subcode = ? AND c.reg_id = ?
                    ORDER BY co.co_number ASC, co.id ASC
                ");
                $offStmt->bind_param("si", $subcode, $regId);
                $offStmt->execute();
                $allOfferingCos = $offStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $offStmt->close();

                if (empty($allOfferingCos)) continue;

                // Pick the cleanest unique CO per co_number
                $uniqueCosByNumber = [];
                $coIdToNumber = [];
                foreach ($allOfferingCos as $row) {
                    $num = (int)$row['co_number'];
                    $coIdToNumber[(int)$row['id']] = $num;
                    if (!isset($uniqueCosByNumber[$num]) || strlen($row['co_description']) > strlen($uniqueCosByNumber[$num]['co_description'])) {
                        $uniqueCosByNumber[$num] = $row;
                    }
                }

                // 3. Upsert Master COs across ALL matching curriculum subjects
                $masterCoIds = []; // co_number => master_co_id
                if (!empty($matchingCurrSubIds)) {
                    foreach ($matchingCurrSubIds as $csId) {
                        foreach ($uniqueCosByNumber as $num => $coData) {
                            $chk = $this->conn->prepare("SELECT id FROM course_outcomes WHERE curr_sub_id = ? AND co_number = ? AND sub_id IS NULL LIMIT 1");
                            $chk->bind_param("ii", $csId, $num);
                            $chk->execute();
                            $existingMasterId = $chk->get_result()->fetch_assoc()['id'] ?? null;
                            $chk->close();

                            if ($existingMasterId) {
                                $masterCoIds[$num] = (int)$existingMasterId;
                            } elseif (!$dryRun) {
                                $ins = $this->conn->prepare("
                                    INSERT INTO course_outcomes (curr_sub_id, sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                                    VALUES (?, NULL, ?, ?, ?, ?)
                                ");
                                $ins->bind_param("iissd", $csId, $num, $coData['co_description'], $coData['bloom_level'], $coData['target_threshold_percent']);
                                $ins->execute();
                                $newMasterId = $this->conn->insert_id;
                                $ins->close();
                                $masterCoIds[$num] = (int)$newMasterId;
                                $report['master_cos_created']++;
                            } else {
                                $report['master_cos_created']++;
                            }
                        }
                    }
                }

                // 4. Backfill sibling offering COs if missing
                $allOfferings = $this->getMatchingOfferingSubjects($matchingCurrSubIds, $subcode, $regId);
                foreach ($allOfferings as $off) {
                    $offSubId = (int)$off['id'];
                    $offCurrId = !empty($off['curr_sub_id']) ? (int)$off['curr_sub_id'] : ($matchingCurrSubIds[0] ?? null);

                    foreach ($uniqueCosByNumber as $num => $coData) {
                        $chkOffCo = $this->conn->prepare("SELECT id FROM course_outcomes WHERE sub_id = ? AND co_number = ? LIMIT 1");
                        $chkOffCo->bind_param("ii", $offSubId, $num);
                        $chkOffCo->execute();
                        $offCoId = $chkOffCo->get_result()->fetch_assoc()['id'] ?? null;
                        $chkOffCo->close();

                        if (!$offCoId) {
                            if (!$dryRun) {
                                $insOffCo = $this->conn->prepare("
                                    INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                                    VALUES (?, ?, ?, ?, ?, ?)
                                ");
                                $insOffCo->bind_param("iiissd", $offSubId, $offCurrId, $num, $coData['co_description'], $coData['bloom_level'], $coData['target_threshold_percent']);
                                $insOffCo->execute();
                                $insOffCo->close();
                                $report['sibling_cos_backfilled']++;
                            } else {
                                $report['sibling_cos_backfilled']++;
                            }
                        }
                    }
                }

                // 5. Propagate canonical logical matrix (translated strictly by canonical PO code per branch)
                $logicalMatrix = $this->findLogicalMatrixBySubjectCode($subcode, $regId);
                if (!empty($logicalMatrix)) {
                    if (!$dryRun) {
                        $propRes = $this->propagateLogicalMatrixBySubjectCode($subcode, $regId, $logicalMatrix);
                        $report['master_mappings_created'] += $propRes['masters_updated'];
                        $report['sibling_mappings_backfilled'] += $propRes['offerings_updated'];
                    } else {
                        $report['master_mappings_created'] += count($logicalMatrix);
                    }
                }
            }
        } catch (Exception $e) {
            $report['error'] = $e->getMessage();
            $this->logs->errLog("CourseOutcomeSyncService::migrateExistingLegacyData Error: " . $e->getMessage());
        }

        return $report;
    }

    /**
     * Scans all (subcode, reg_id) groups across curriculum_subjects and class offerings.
     * If ANY entity in the group has an articulation matrix, auto-populates it across
     * all curriculum subjects and class offerings in that group that are missing it.
     */
    public function autoPopulateAllMissingMatricesBySubjectCode(): array
    {
        $stats = [
            'groups_checked' => 0,
            'groups_populated' => 0,
            'masters_updated' => 0,
            'offerings_updated' => 0,
            'cells_written' => 0
        ];

        $q = $this->conn->query("
            SELECT DISTINCT subcode, reg_id 
            FROM curriculum_subjects 
            WHERE subcode IS NOT NULL AND subcode != '' AND reg_id IS NOT NULL AND reg_id > 0
        ");
        $groups = $q ? $q->fetch_all(MYSQLI_ASSOC) : [];

        foreach ($groups as $g) {
            $subcode = trim($g['subcode']);
            $regId = (int)$g['reg_id'];
            $stats['groups_checked']++;

            $logicalMatrix = $this->findLogicalMatrixBySubjectCode($subcode, $regId);
            if (!empty($logicalMatrix)) {
                $propRes = $this->propagateLogicalMatrixBySubjectCode($subcode, $regId, $logicalMatrix);
                if (!empty($propRes['cells_written'])) {
                    $stats['groups_populated']++;
                    $stats['masters_updated'] += $propRes['masters_updated'];
                    $stats['offerings_updated'] += $propRes['offerings_updated'];
                    $stats['cells_written'] += $propRes['cells_written'];
                }
            }
        }

        return $stats;
    }

    /**
     * Cleans up all duplicate Master COs and orphan mappings.
     * Keeps the lowest canonical ID for each (curr_sub_id, co_number) and eliminates duplicate rows.
     */
    public function cleanupDuplicateMasterCOsAndMappings(): array
    {
        $stats = [
            'duplicate_cos_removed' => 0,
            'orphan_mappings_removed' => 0
        ];

        // 1. Delete cross-branch invalid offering mappings (where PO neither matches class spec_id nor dept_id, and regulation doesn't match)
        $this->conn->query("
            DELETE m FROM co_po_mapping m
            JOIN course_outcomes co ON m.co_id = co.id
            JOIN subjects s ON co.sub_id = s.id
            JOIN classes c ON s.class_id = c.id
            LEFT JOIN specialization sp ON c.spec_id = sp.id
            LEFT JOIN regulations r ON c.reg_id = r.id
            JOIN po_pso p ON m.po_id = p.id
            WHERE ((p.reg_id != c.reg_id AND p.regulation != r.regulation) AND p.reg_id != 0)
               OR ((p.specid != c.spec_id AND p.specid != sp.dept_id) AND p.specid != 0)
        ");
        $stats['orphan_mappings_removed'] += max(0, (int)$this->conn->affected_rows);

        // 2. Delete cross-branch invalid master mappings (where PO neither matches curriculum spec_id nor dept_id, and regulation doesn't match)
        $this->conn->query("
            DELETE m FROM co_po_mapping m
            JOIN course_outcomes co ON m.co_id = co.id
            JOIN curriculum_subjects cs ON co.curr_sub_id = cs.id
            LEFT JOIN specialization sp ON cs.spec_id = sp.id
            LEFT JOIN regulations r ON cs.reg_id = r.id
            JOIN po_pso p ON m.po_id = p.id
            WHERE co.sub_id IS NULL AND (
                  ((p.reg_id != cs.reg_id AND p.regulation != r.regulation) AND p.reg_id != 0)
               OR ((p.specid != cs.spec_id AND p.specid != sp.dept_id) AND p.specid != 0)
            )
        ");
        $stats['orphan_mappings_removed'] += max(0, (int)$this->conn->affected_rows);

        // 3. Delete redundant mappings where a CO is mapped to multiple PO rows with the same code (e.g. across academic years)
        $this->conn->query("
            DELETE m FROM co_po_mapping m
            JOIN po_pso p ON m.po_id = p.id
            WHERE m.id NOT IN (
                SELECT max_map_id FROM (
                    SELECT MAX(m2.id) as max_map_id
                    FROM co_po_mapping m2
                    JOIN po_pso p2 ON m2.po_id = p2.id
                    GROUP BY m2.co_id, p2.code
                ) t
            )
        ");
        $stats['orphan_mappings_removed'] += max(0, (int)$this->conn->affected_rows);

        // 4. Identify duplicate master COs (curr_sub_id IS NOT NULL AND sub_id IS NULL)
        $dupQuery = "
            SELECT id
            FROM course_outcomes
            WHERE curr_sub_id IS NOT NULL AND sub_id IS NULL
            AND id NOT IN (
                SELECT min_id FROM (
                    SELECT MIN(id) as min_id
                    FROM course_outcomes
                    WHERE curr_sub_id IS NOT NULL AND sub_id IS NULL
                    GROUP BY curr_sub_id, co_number
                ) t
            )
        ";
        $dupRes = $this->conn->query($dupQuery);
        $duplicateCoIds = [];
        if ($dupRes) {
            while ($row = $dupRes->fetch_assoc()) {
                $duplicateCoIds[] = (int)$row['id'];
            }
        }

        if (!empty($duplicateCoIds)) {
            $chunks = array_chunk($duplicateCoIds, 1000);
            foreach ($chunks as $chunk) {
                $idList = implode(',', $chunk);
                // Delete duplicate mappings pointing to duplicate COs
                $this->conn->query("DELETE FROM co_po_mapping WHERE co_id IN ({$idList})");
                $stats['orphan_mappings_removed'] += $this->conn->affected_rows;

                // Delete duplicate COs
                $this->conn->query("DELETE FROM course_outcomes WHERE id IN ({$idList})");
                $stats['duplicate_cos_removed'] += $this->conn->affected_rows;
            }
        }

        // 2. Remove orphan mappings whose co_id is completely missing from course_outcomes
        $this->conn->query("DELETE FROM co_po_mapping WHERE co_id NOT IN (SELECT id FROM course_outcomes)");
        if ($this->conn->affected_rows > 0) {
            $stats['orphan_mappings_removed'] += $this->conn->affected_rows;
        }

        return $stats;
    }

    /**
     * Enforces the generated unique constraint on course_outcomes for master COs
     * so that MySQL physically rejects duplicate (curr_sub_id, co_number) master rows.
     */
    public function enforceMasterCoUniqueConstraint(): bool
    {
        try {
            // First cleanup any existing duplicates so index creation succeeds
            $this->cleanupDuplicateMasterCOsAndMappings();

            // Check if column master_co_scope already exists
            $colRes = $this->conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'course_outcomes' AND COLUMN_NAME = 'master_co_scope'")->fetch_row()[0];
            if ($colRes == 0) {
                $this->conn->query("ALTER TABLE course_outcomes ADD COLUMN master_co_scope varchar(64) GENERATED ALWAYS AS (IF(sub_id IS NULL, CONCAT('M_', curr_sub_id, '_', co_number), NULL)) STORED");
            }

            // Check if index unique_master_co already exists
            $idxRes = $this->conn->query("SHOW INDEX FROM course_outcomes WHERE Key_name = 'unique_master_co'")->num_rows;
            if ($idxRes == 0) {
                $this->conn->query("ALTER TABLE course_outcomes ADD UNIQUE KEY unique_master_co (master_co_scope)");
            }
            return true;
        } catch (Exception $e) {
            $this->logs->errLog("enforceMasterCoUniqueConstraint: " . $e->getMessage());
            return false;
        }
    }
}

