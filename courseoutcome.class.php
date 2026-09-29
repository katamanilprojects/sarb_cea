<?php
// courseoutcome.class.php - Modular Course Outcomes & PO/PSO Articulation Mapping
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait CourseOutcomeTrait
{
    protected $coClassname = "CourseOutcome";

    /**
     * Function to get Course Outcomes (COs) by subject ID
     */
    public function getCOsBySubjectId($sub_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->coClassname . " - getCOsBySubjectId - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `co_number`, `co_description`, `bloom_level`, `target_threshold_percent` FROM `course_outcomes` WHERE `sub_id` = ? ORDER BY `co_number` ASC");
            if (!$stmt) {
                throw new Exception("Failed to prepare getCOsBySubjectId statement: " . $this->conn->error);
            }

            $stmt->bind_param("i", $sub_id);
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                $res['status'] = 1;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();

            // If no local COs defined, fallback to master curriculum COs if linked
            if (empty($res['data'])) {
                $stmtMaster = $this->conn->prepare("
                    SELECT co.id, co.co_number, co.co_description, co.bloom_level, co.target_threshold_percent, 1 as is_master
                    FROM course_outcomes co
                    JOIN subjects s ON s.curr_sub_id = co.curr_sub_id
                    WHERE s.id = ? AND co.sub_id IS NULL
                    ORDER BY co.co_number ASC
                ");
                if ($stmtMaster) {
                    $stmtMaster->bind_param("i", $sub_id);
                    $stmtMaster->execute();
                    $masterRows = $stmtMaster->get_result()->fetch_all(MYSQLI_ASSOC);
                    if (!empty($masterRows)) {
                        $res['data'] = $masterRows;
                        $res['is_inherited'] = true;
                    }
                    $stmtMaster->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    public function importMasterCOsToSubject(int $sub_id): array
    {
        $res = ['status' => 0, 'copied' => 0];
        try {
            $stmt = $this->conn->prepare("SELECT curr_sub_id FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $sub_id);
            $stmt->execute();
            $curr_sub_id = $stmt->get_result()->fetch_assoc()['curr_sub_id'] ?? null;
            $stmt->close();

            if (!$curr_sub_id) {
                $res['error'] = "No curriculum subject mapped to this subject.";
                return $res;
            }

            $copyStmt = $this->conn->prepare("
                INSERT INTO course_outcomes (sub_id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent)
                SELECT ?, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent
                FROM course_outcomes
                WHERE curr_sub_id = ? AND sub_id IS NULL
                ON DUPLICATE KEY UPDATE 
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent),
                    curr_sub_id = VALUES(curr_sub_id)
            ");
            $copyStmt->bind_param("ii", $sub_id, $curr_sub_id);
            $copyStmt->execute();
            $res['status'] = 1;
            $res['copied'] = $copyStmt->affected_rows;
            $copyStmt->close();
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("importMasterCOsToSubject Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Function to add a new Course Outcome (CO)
     */
    public function addCO($sub_id, $co_number, $co_description, $bloom_level = 'L3-Apply', $target_threshold = 60.0)
    {
        $res = ['status' => 0];
        $myname = $this->coClassname . " - addCO - ";

        try {
            $stmt = $this->conn->prepare("
                INSERT INTO `course_outcomes` (`sub_id`, `co_number`, `co_description`, `bloom_level`, `target_threshold_percent`) 
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    co_description = VALUES(co_description),
                    bloom_level = VALUES(bloom_level),
                    target_threshold_percent = VALUES(target_threshold_percent)
            ");
            if (!$stmt) {
                throw new Exception("Failed to prepare addCO statement: " . $this->conn->error);
            }

            $stmt->bind_param("iissd", $sub_id, $co_number, $co_description, $bloom_level, $target_threshold);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['insert_id'] = $this->conn->insert_id;
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }

        return $res;
    }

    /**
     * Fetches POs/PSOs relevant to the class/regulation for a subject
     */
    public function getRelevantPoPso($sub_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->coClassname . " - getRelevantPoPso - ";
        $spec_id = null;
        $regulation = null;

        try {
            $query_class_details = "SELECT c.spec_id, r.regulation, c.acad_year, c.reg_id
                                    FROM subjects s
                                    JOIN classes c ON s.class_id = c.id
                                    JOIN regulations r ON c.reg_id = r.id
                                    WHERE s.id = ?";
            $stmt1 = $this->conn->prepare($query_class_details);
            if (!$stmt1) { throw new Exception("Prepare failed (stmt1): " . $this->conn->error); }
            $stmt1->bind_param("i", $sub_id);
            if (!$stmt1->execute()) { throw new Exception("Execute failed (stmt1): " . $stmt1->error); }
            $stmt1->bind_result($spec_id, $regulation, $acad_year, $reg_id);
            $details_found = $stmt1->fetch();
            $stmt1->close();

            if ($details_found && $spec_id && $regulation) {
                $query_pops = "SELECT `id`, `code`, `description`, `po_pso`, `target_score`
                               FROM `po_pso`
                               WHERE `acad_year` = ? AND `specid` = ? AND `regulation` = ?
                               ORDER BY `po_pso` ASC, `orderid` ASC, `id` ASC";
                $stmt2 = $this->conn->prepare($query_pops);
                if (!$stmt2) { throw new Exception("Prepare failed (stmt2): " . $this->conn->error); }
                $stmt2->bind_param("sis", $acad_year, $spec_id, $regulation);
                if ($stmt2->execute()) {
                    $result = $stmt2->get_result();
                    $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                    $res['status'] = 1;
                } else {
                    $this->logs->errLog($myname . "Execute failed (stmt2): " . $stmt2->error);
                }
                $stmt2->close();

                // If no exact acad_year match, fallback to versioned effective_from_year for that regulation
                if (empty($res['data']) && $reg_id) {
                    $yearVal = (int)substr($acad_year, 0, 4);
                    $query_versioned = "
                        SELECT p.`id`, p.`code`, p.`description`, p.`po_pso`, p.`target_score`
                        FROM `po_pso` p
                        WHERE p.`reg_id` = ? AND p.`specid` = ?
                          AND p.`effective_from_year` = (
                              SELECT MAX(sub.effective_from_year) 
                              FROM po_pso sub 
                              WHERE sub.reg_id = p.reg_id AND sub.specid = p.specid 
                                AND sub.code = p.code AND sub.effective_from_year <= ?
                          )
                        ORDER BY p.`po_pso` ASC, p.`orderid` ASC, p.`id` ASC
                    ";
                    $stmt3 = $this->conn->prepare($query_versioned);
                    if ($stmt3) {
                        $stmt3->bind_param("iii", $reg_id, $spec_id, $yearVal);
                        $stmt3->execute();
                        $res['data'] = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);
                        $res['status'] = 1;
                        $stmt3->close();
                    }
                }
            } else {
                $this->logs->warningLog($myname . "Could not find spec ID/regulation for subject ID: $sub_id");
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Fetches existing CO-PO/PSO mappings with weightage (1, 2, or 3)
     */
    public function getCoPoMappings($co_ids, $po_pso_ids)
    {
        $mappings = [];
        if (empty($co_ids) || empty($po_pso_ids)) {
            return $mappings;
        }
        $myname = $this->coClassname . " - getCoPoMappings - ";

        $co_placeholders = implode(',', array_fill(0, count($co_ids), '?'));
        $po_pso_placeholders = implode(',', array_fill(0, count($po_pso_ids), '?'));
        $types = str_repeat('i', count($co_ids) + count($po_pso_ids));
        $params = array_merge($co_ids, $po_pso_ids);

        try {
            $query = "SELECT `co_id`, `po_id`, `weightage` FROM `co_po_mapping` WHERE `co_id` IN ($co_placeholders) AND `po_id` IN ($po_pso_placeholders)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    if (isset($row['weightage']) && in_array($row['weightage'], [1, 2, 3])) {
                        $mappings[$row['co_id'] . '-' . $row['po_id']] = (int)$row['weightage'];
                    }
                }
            } else {
                $this->logs->errLog($myname . "Execute failed: " . $stmt->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $mappings;
    }

    /**
     * Adds or updates a single CO-PO/PSO mapping with specific weightage (1, 2, or 3)
     */
    public function addUpdateCoPoMapping($co_id, $po_pso_id, $weightage)
    {
        if (!in_array($weightage, [1, 2, 3])) {
            $this->logs->warningLog("Invalid weightage value ($weightage) provided for CO_ID $co_id, PO_ID $po_pso_id.");
            return false;
        }

        $myname = $this->coClassname . " - addUpdateCoPoMapping - ";
        try {
            $stmt = $this->conn->prepare("INSERT INTO co_po_mapping (co_id, po_id, weightage) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE weightage = ?");
            if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }

            $stmt->bind_param("iiii", $co_id, $po_pso_id, $weightage, $weightage);

            if ($stmt->execute()) {
                $stmt->close();
                return true;
            } else {
                $this->logs->errLog($myname . "Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Helper to get a map of CO Numbers to CO IDs for a specific subject
     */
    public function getCoMapByNumber($sub_id)
    {
        $co_map = [];
        $stmt = $this->conn->prepare("SELECT id, co_number FROM course_outcomes WHERE sub_id = ?");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $stmt->bind_result($co_id, $co_number);
        while ($stmt->fetch()) {
            $co_map[$co_number] = $co_id;
        }
        $stmt->close();
        return $co_map;
    }

    /**
     * Helper to get the CO numbers (e.g., [1, 3]) for a source question
     */
    public function getSourceQuestionCoNumbers($question_id)
    {
        $co_numbers = [];
        $stmt = $this->conn->prepare("SELECT co.co_number FROM question_co_mapping qcm JOIN course_outcomes co ON qcm.co_id = co.id WHERE qcm.question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($co_number);
        while ($stmt->fetch()) {
            $co_numbers[] = $co_number;
        }
        $stmt->close();
        return $co_numbers;
    }
}

class CourseOutcome extends DBCredentials
{
    use CourseOutcomeTrait;

    public function __construct()
    {
        parent::__construct();
    }
}
