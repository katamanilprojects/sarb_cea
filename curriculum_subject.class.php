<?php
require_once("user.class.php");
require_once("programs.class.php");
require_once("regulations.class.php");
require_once("superadmin.class.php");

class CurriculumSubject extends User
{
    private $classname = "CurriculumSubject";

    public function __construct()
    {
        parent::__construct();
    }

    public function getAllPrograms()
    {
        $programsObj = new Programs();
        return $programsObj->getAllPrograms();
    }

    public function getAllRegulations()
    {
        $regulationsObj = new Regulations();
        return $regulationsObj->getAllRegulations();
    }

    public function getRegulationsByProgramId($progId)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getRegulationsByProgramId - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT r.id, r.regulation, r.prog_id, p.prog_shortname
                    FROM regulations r
                    JOIN programs p ON r.prog_id = p.id
                    WHERE r.prog_id = ?
                    ORDER BY r.regulation ASC";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $progId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch regulations for program.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getAllSpecializations()
    {
        $superadmin = new SuperAdmin();
        return $superadmin->getAllSpecializations();
    }

    public function getSpecializationsByProgramId($progId)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getSpecializationsByProgramId - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT id, spec_code, spec_shortname, spec_fullname, prog_id
                    FROM specialization
                    WHERE prog_id = ? AND status = 1
                    ORDER BY spec_fullname ASC";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $progId);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch specializations.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getYearSemOptions()
    {
        return [
            "I Yr - I Sem",
            "II Yr - I Sem",
            "III Yr - I Sem",
            "IV Yr - I Sem",
            "I Yr - II Sem",
            "II Yr - II Sem",
            "III Yr - II Sem",
            "IV Yr - II Sem",
            "I Sem",
            "II Sem",
            "III Sem",
            "IV Sem"
        ];
    }

    public function getSubjectsByContext($progId, $regId, $specId, $yearsem)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getSubjectsByContext - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT cs.*, p.prog_shortname, r.regulation, s.spec_shortname, s.spec_fullname 
                    FROM curriculum_subjects cs
                    JOIN programs p ON cs.prog_id = p.id
                    JOIN regulations r ON cs.reg_id = r.id
                    JOIN specialization s ON cs.spec_id = s.id
                    WHERE cs.prog_id = ? AND cs.reg_id = ? AND cs.spec_id = ? AND cs.yearsem = ?
                    ORDER BY cs.status DESC, cs.subject_sno ASC, cs.subcode ASC";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("iiis", $progId, $regId, $specId, $yearsem);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch curriculum subjects.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getSubjectsForClass($regId, $specId, $yearsem)
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        $myname = $this->classname . " - getSubjectsForClass - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT id, subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits
                    FROM curriculum_subjects
                    WHERE reg_id = ? AND spec_id = ? AND yearsem = ? AND status = 1
                    ORDER BY subject_sno ASC, subcode ASC";

            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("iis", $regId, $specId, $yearsem);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $res['data'][] = $row;
            }
            $stmt->close();

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch curriculum subjects for class.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function getSubjectById($id)
    {
        $res = ['status' => 0, 'data' => null, 'error' => ''];
        $myname = $this->classname . " - getSubjectById - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $sql = "SELECT * FROM curriculum_subjects WHERE id = ?";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("i", $id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['data'] = $row;
                $res['status'] = 1;
            } else {
                $res['error'] = 'Subject not found.';
            }
            $stmt->close();
        } catch (Exception $e) {
            $res['error'] = 'Failed to fetch subject.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function lookupSubjectByCodeAndReg($subcode, $regId)
    {
        $res = ['status' => 0, 'data' => null, 'error' => ''];
        $myname = $this->classname . " - lookupSubjectByCodeAndReg - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $cleanCode = trim($subcode);
            $regId = (int)$regId;

            // First check in curriculum_subjects matching the same regulation
            $sql = "SELECT subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits
                    FROM curriculum_subjects
                    WHERE subcode = ? AND reg_id = ?
                    ORDER BY id DESC LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }

            $stmt->bind_param("si", $cleanCode, $regId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($row = $result->fetch_assoc()) {
                $res['data'] = $row;
                $res['status'] = 1;
                $stmt->close();
                return $res;
            }
            $stmt->close();

            // If not found in curriculum_subjects, search any regulation in curriculum_subjects
            $sql2 = "SELECT subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits
                     FROM curriculum_subjects
                     WHERE subcode = ?
                     ORDER BY id DESC LIMIT 1";
            $stmt2 = $this->conn->prepare($sql2);
            if ($stmt2) {
                $stmt2->bind_param("s", $cleanCode);
                $stmt2->execute();
                $result2 = $stmt2->get_result();
                if ($row2 = $result2->fetch_assoc()) {
                    $res['data'] = $row2;
                    $res['status'] = 1;
                    $stmt2->close();
                    return $res;
                }
                $stmt2->close();
            }

            // Finally, fall back to existing subjects table if it was ever taught previously
            $sql3 = "SELECT subcode, sub_fullname, sub_shortname, sub_type
                     FROM subjects
                     WHERE subcode = ?
                     ORDER BY id DESC LIMIT 1";
            $stmt3 = $this->conn->prepare($sql3);
            if ($stmt3) {
                $stmt3->bind_param("s", $cleanCode);
                $stmt3->execute();
                $result3 = $stmt3->get_result();
                if ($row3 = $result3->fetch_assoc()) {
                    $row3['course_category'] = '';
                    $row3['lecture_hours'] = 0.0;
                    $row3['tutorial_hours'] = 0.0;
                    $row3['pr_hours'] = 0.0;
                    $row3['practical_hours'] = 0.0;
                    $row3['credits'] = 0.0;
                    $res['data'] = $row3;
                    $res['status'] = 1;
                    $stmt3->close();
                    return $res;
                }
                $stmt3->close();
            }

            $res['error'] = 'Subject code not found.';
        } catch (Exception $e) {
            $res['error'] = 'Failed to lookup subject code.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function addOrUpdateSubject($data)
    {
        $res = ['status' => 0, 'message' => '', 'error' => ''];
        $myname = $this->classname . " - addOrUpdateSubject - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $id = !empty($data['id']) ? (int)$data['id'] : 0;
            $progId = (int)($data['prog_id'] ?? 0);
            $regId = (int)($data['reg_id'] ?? 0);
            $specId = (int)($data['spec_id'] ?? 0);
            $yearsem = trim($data['yearsem'] ?? '');
            $subjectSno = (int)($data['subject_sno'] ?? 1);
            $subcode = strtoupper(trim($data['subcode'] ?? ''));
            $subFullname = trim($data['sub_fullname'] ?? '');
            $subShortname = strtoupper(trim($data['sub_shortname'] ?? ''));
            $subType = trim($data['sub_type'] ?? 'Theory');
            $courseCategory = strtoupper(trim($data['course_category'] ?? ''));
            $lectureHours = (float)($data['lecture_hours'] ?? 0.0);
            $tutorialHours = (float)($data['tutorial_hours'] ?? 0.0);
            $prHours = (float)($data['pr_hours'] ?? 0.0);
            $practicalHours = (float)($data['practical_hours'] ?? 0.0);
            $credits = (float)($data['credits'] ?? 0.0);

            if (empty($progId) || empty($regId) || empty($specId) || empty($yearsem) || empty($subcode) || empty($subFullname) || empty($subShortname)) {
                $res['error'] = 'Please fill all required fields.';
                return $res;
            }

            if ($id > 0) {
                // Update
                $sql = "UPDATE curriculum_subjects 
                        SET subject_sno = ?, subcode = ?, sub_fullname = ?, sub_shortname = ?, sub_type = ?, course_category = ?, 
                            lecture_hours = ?, tutorial_hours = ?, pr_hours = ?, practical_hours = ?, credits = ?, status = 1
                        WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->conn->error);
                }
                $stmt->bind_param("isssssdddddi", $subjectSno, $subcode, $subFullname, $subShortname, $subType, $courseCategory, $lectureHours, $tutorialHours, $prHours, $practicalHours, $credits, $id);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                $res['status'] = 1;
                $res['message'] = 'Curriculum subject updated successfully.';
                $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                $this->dbActivityLog($acadId, "UPDATE_CURRICULUM_SUBJECT", "Updated curriculum subject ID $id: $subcode ($subFullname)", "academic_section", "CURRICULUM_SUBJECT", (string)$id);
            } else {
                // Check if already exists for this context
                $checkSql = "SELECT id FROM curriculum_subjects WHERE reg_id = ? AND spec_id = ? AND yearsem = ? AND subcode = ?";
                $checkStmt = $this->conn->prepare($checkSql);
                $checkStmt->bind_param("iiss", $regId, $specId, $yearsem, $subcode);
                $checkStmt->execute();
                $checkRes = $checkStmt->get_result();
                if ($existing = $checkRes->fetch_assoc()) {
                    // Update existing
                    $checkStmt->close();
                    $existingId = (int)$existing['id'];
                    $updateSql = "UPDATE curriculum_subjects 
                                  SET subject_sno = ?, sub_fullname = ?, sub_shortname = ?, sub_type = ?, course_category = ?, 
                                      lecture_hours = ?, tutorial_hours = ?, pr_hours = ?, practical_hours = ?, credits = ?, status = 1
                                  WHERE id = ?";
                    $uStmt = $this->conn->prepare($updateSql);
                    $uStmt->bind_param("issssdddddi", $subjectSno, $subFullname, $subShortname, $subType, $courseCategory, $lectureHours, $tutorialHours, $prHours, $practicalHours, $credits, $existingId);
                    $uStmt->execute();
                    $uStmt->close();
                    $res['status'] = 1;
                    $res['message'] = 'Curriculum subject already existed for this course and was updated successfully.';
                    $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $this->dbActivityLog($acadId, "UPDATE_CURRICULUM_SUBJECT", "Updated existing curriculum subject ID $existingId: $subcode ($subFullname)", "academic_section", "CURRICULUM_SUBJECT", (string)$existingId);
                } else {
                    $checkStmt->close();
                    // Insert
                    $sql = "INSERT INTO curriculum_subjects 
                            (prog_id, reg_id, spec_id, yearsem, subject_sno, subcode, sub_fullname, sub_shortname, sub_type, course_category, lecture_hours, tutorial_hours, pr_hours, practical_hours, credits, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $this->conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $this->conn->error);
                    }
                    $stmt->bind_param("iiisisssssddddd", $progId, $regId, $specId, $yearsem, $subjectSno, $subcode, $subFullname, $subShortname, $subType, $courseCategory, $lectureHours, $tutorialHours, $prHours, $practicalHours, $credits);
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    $newSubId = $this->conn->insert_id;
                    $stmt->close();
                    $res['status'] = 1;
                    $res['message'] = 'Curriculum subject added successfully.';
                    $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
                    $this->dbActivityLog($acadId, "ADD_CURRICULUM_SUBJECT", "Added curriculum subject $subcode ($subFullname)", "academic_section", "CURRICULUM_SUBJECT", (string)$newSubId);
                }
            }
        } catch (Exception $e) {
            $res['error'] = 'Failed to save subject: ' . $e->getMessage();
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function toggleStatusSubject($id, $newStatus = null)
    {
        $res = ['status' => 0, 'message' => '', 'error' => ''];
        $myname = $this->classname . " - toggleStatusSubject - ";

        try {
            if (empty($this->conn)) {
                $res['error'] = 'Database connection failed.';
                return $res;
            }

            $id = (int)$id;
            if ($newStatus === null) {
                $sql = "UPDATE curriculum_subjects SET status = IF(status = 1, 0, 1) WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("i", $id);
            } else {
                $newStatus = (int)$newStatus;
                $sql = "UPDATE curriculum_subjects SET status = ? WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                $stmt->bind_param("ii", $newStatus, $id);
            }

            if (!$stmt) {
                throw new Exception("Prepare failed: " . $this->conn->error);
            }
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }
            $stmt->close();

            $res['status'] = 1;
            $res['message'] = 'Subject status updated successfully.';
            $acadId = $_SESSION['userid'] ?? ($_SESSION['user_id'] ?? 0);
            $this->dbActivityLog($acadId, "TOGGLE_CURRICULUM_SUBJECT_STATUS", "Curriculum subject ID $id status updated to " . ($newStatus !== null ? $newStatus : 'toggled'), "academic_section", "CURRICULUM_SUBJECT", (string)$id);
        } catch (Exception $e) {
            $res['error'] = 'Failed to update subject status.';
            $this->logs->errLog($myname . $e->getMessage());
        }

        return $res;
    }

    public function deleteSubject($id)
    {
        // Redirect legacy delete calls to inactivate (soft-delete)
        return $this->toggleStatusSubject($id, 0);
    }

    public function getMasterCOs(int $curr_sub_id): array
    {
        $res = ['status' => 0, 'data' => [], 'error' => ''];
        try {
            $stmt = $this->conn->prepare("
                SELECT id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent
                FROM course_outcomes 
                WHERE curr_sub_id = ? AND sub_id IS NULL 
                ORDER BY co_number ASC
            ");
            $stmt->bind_param("i", $curr_sub_id);
            $stmt->execute();
            $res['data'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            // If empty, auto-discover from sibling curriculum subjects or offering subjects sharing same subcode & reg_id
            if (empty($res['data'])) {
                require_once __DIR__ . '/services/CourseOutcomeSyncService.php';
                $sync = CourseOutcomeSyncService::getInstance();
                $cSub = $this->getSubjectById($curr_sub_id)['data'] ?? null;
                if ($cSub && !empty($cSub['subcode']) && !empty($cSub['reg_id'])) {
                    $sourceDefs = $sync->getSourceCoDefinitions(trim($cSub['subcode']), (int)$cSub['reg_id']);
                    if (!empty($sourceDefs)) {
                        $sync->ensureMasterCOsExist($curr_sub_id, $sourceDefs);
                        $stmt2 = $this->conn->prepare("
                            SELECT id, curr_sub_id, co_number, co_description, bloom_level, target_threshold_percent
                            FROM course_outcomes 
                            WHERE curr_sub_id = ? AND sub_id IS NULL 
                            ORDER BY co_number ASC
                        ");
                        $stmt2->bind_param("i", $curr_sub_id);
                        $stmt2->execute();
                        $res['data'] = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                        $stmt2->close();
                    }
                }
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::getMasterCOs Error: " . $e->getMessage());
        }
        return $res;
    }

    public function addOrUpdateMasterCO(int $curr_sub_id, int $co_number, string $co_description, string $bloom_level = 'L3-Apply', float $target_threshold = 60.0): array
    {
        $res = ['status' => 0, 'error' => ''];
        try {
            require_once __DIR__ . '/services/CourseOutcomeSyncService.php';
            $syncRes = CourseOutcomeSyncService::getInstance()->syncMasterCOToOfferings(
                $curr_sub_id,
                $co_number,
                $co_description,
                $bloom_level,
                $target_threshold
            );

            if (!empty($syncRes['status'])) {
                $res['status'] = 1;
                $res['message'] = "Master Course Outcome saved successfully. " . 
                    ($syncRes['offerings_updated'] > 0 ? "Cascaded to {$syncRes['offerings_updated']} active offering(s)." : "");
            } else {
                throw new Exception($syncRes['error'] ?? "Failed to save master CO");
            }
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::addOrUpdateMasterCO Error: " . $e->getMessage());
        }
        return $res;
    }

    public function deleteMasterCO(int $curr_sub_id, int $co_id): array
    {
        $res = ['status' => 0, 'error' => ''];
        try {
            $stmt = $this->conn->prepare("DELETE FROM course_outcomes WHERE id = ? AND curr_sub_id = ? AND sub_id IS NULL");
            $stmt->bind_param("ii", $co_id, $curr_sub_id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Master Course Outcome deleted successfully.";
            } else {
                throw new Exception($stmt->error);
            }
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::deleteMasterCO Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Resolves POs and PSOs for a curriculum subject
     */
    public function getRelevantPoPsoForCurriculum(int $curr_sub_id): array
    {
        $res = [];
        try {
            $stmt = $this->conn->prepare("
                SELECT cs.reg_id, cs.spec_id, cs.prog_id, r.regulation, sp.dept_id
                FROM curriculum_subjects cs
                JOIN regulations r ON cs.reg_id = r.id
                LEFT JOIN specialization sp ON cs.spec_id = sp.id
                WHERE cs.id = ?
            ");
            $stmt->bind_param("i", $curr_sub_id);
            $stmt->execute();
            $cs = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$cs) return [];

            // 1. Direct match on reg_id and spec_id
            $q1 = "
                SELECT id, code, description, po_pso, target_score
                FROM po_pso
                WHERE (reg_id = ? OR regulation = ?) AND specid = ?
                GROUP BY code
                ORDER BY po_pso ASC, orderid ASC, id ASC
            ";
            $s1 = $this->conn->prepare($q1);
            $s1->bind_param("isi", $cs['reg_id'], $cs['regulation'], $cs['spec_id']);
            $s1->execute();
            $res = $s1->get_result()->fetch_all(MYSQLI_ASSOC);
            $s1->close();
            if (!empty($res)) return $res;

            // 2. Match via class spec_id from any existing offerings
            $q2 = "
                SELECT p.id, p.code, p.description, p.po_pso, p.target_score
                FROM po_pso p
                JOIN classes c ON p.specid = c.spec_id
                JOIN subjects s ON s.class_id = c.id
                WHERE s.curr_sub_id = ? AND (p.reg_id = ? OR p.regulation = ?)
                GROUP BY p.code
                ORDER BY p.po_pso ASC, p.orderid ASC, p.id ASC
            ";
            $s2 = $this->conn->prepare($q2);
            $s2->bind_param("iis", $curr_sub_id, $cs['reg_id'], $cs['regulation']);
            $s2->execute();
            $res = $s2->get_result()->fetch_all(MYSQLI_ASSOC);
            $s2->close();
            if (!empty($res)) return $res;

            // 3. Match via dept_id
            if (!empty($cs['dept_id'])) {
                $q3 = "
                    SELECT id, code, description, po_pso, target_score
                    FROM po_pso
                    WHERE (reg_id = ? OR regulation = ?) AND specid = ?
                    GROUP BY code
                    ORDER BY po_pso ASC, orderid ASC, id ASC
                ";
                $s3 = $this->conn->prepare($q3);
                $s3->bind_param("isi", $cs['reg_id'], $cs['regulation'], $cs['dept_id']);
                $s3->execute();
                $res = $s3->get_result()->fetch_all(MYSQLI_ASSOC);
                $s3->close();
                if (!empty($res)) return $res;
            }

            // 4. Fallback to regulation-wide POs
            $q4 = "
                SELECT id, code, description, po_pso, target_score
                FROM po_pso
                WHERE (reg_id = ? OR regulation = ?)
                GROUP BY code
                ORDER BY po_pso ASC, orderid ASC, id ASC
            ";
            $s4 = $this->conn->prepare($q4);
            $s4->bind_param("is", $cs['reg_id'], $cs['regulation']);
            $s4->execute();
            $res = $s4->get_result()->fetch_all(MYSQLI_ASSOC);
            $s4->close();
        } catch (Exception $e) {
            $this->logs->errLog("CurriculumSubject::getRelevantPoPsoForCurriculum Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get Master Articulation Matrix for a curriculum subject
     */
    public function getMasterArticulationMatrix(int $curr_sub_id): array
    {
        $res = ['status' => 0, 'cos' => [], 'po_psos' => [], 'mappings' => []];
        try {
            $cosRes = $this->getMasterCOs($curr_sub_id);
            $res['cos'] = $cosRes['data'] ?? [];

            $res['po_psos'] = $this->getRelevantPoPsoForCurriculum($curr_sub_id);

            if (!empty($res['cos']) && !empty($res['po_psos'])) {
                $coIds = array_column($res['cos'], 'id');
                $ph = implode(',', array_fill(0, count($coIds), '?'));
                $types = str_repeat('i', count($coIds));

                $stmt = $this->conn->prepare("
                    SELECT co_id, po_id, weightage
                    FROM co_po_mapping
                    WHERE co_id IN ($ph)
                ");
                $stmt->bind_param($types, ...$coIds);
                $stmt->execute();
                $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $stmt->close();

                $mappings = [];
                foreach ($rows as $r) {
                    $mappings[$r['co_id'] . '-' . $r['po_id']] = (int)$r['weightage'];
                }
                $res['mappings'] = $mappings;

                // If mappings are empty, check if sibling curriculum or offering subject has a matrix
                if (empty($res['mappings'])) {
                    require_once __DIR__ . '/services/CourseOutcomeSyncService.php';
                    $sync = CourseOutcomeSyncService::getInstance();
                    $cSub = $this->getSubjectById($curr_sub_id)['data'] ?? null;
                    if ($cSub && !empty($cSub['subcode']) && !empty($cSub['reg_id'])) {
                        $logical = $sync->findLogicalMatrixBySubjectCode(trim($cSub['subcode']), (int)$cSub['reg_id']);
                        if (!empty($logical)) {
                            $sync->propagateLogicalMatrixBySubjectCode(trim($cSub['subcode']), (int)$cSub['reg_id'], $logical);

                            // Re-fetch mappings now that propagation has populated them
                            $stmt2 = $this->conn->prepare("
                                SELECT co_id, po_id, weightage
                                FROM co_po_mapping
                                WHERE co_id IN ($ph)
                            ");
                            $stmt2->bind_param($types, ...$coIds);
                            $stmt2->execute();
                            $rows2 = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);
                            $stmt2->close();

                            $mappings = [];
                            foreach ($rows2 as $r) {
                                $mappings[$r['co_id'] . '-' . $r['po_id']] = (int)$r['weightage'];
                            }
                            $res['mappings'] = $mappings;
                        }
                    }
                }
            }

            $res['status'] = 1;
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::getMasterArticulationMatrix Error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Save Master Articulation Matrix and cascade to all offerings
     */
    public function saveMasterArticulationMatrix(int $curr_sub_id, array $mappings): array
    {
        $res = ['status' => 0, 'error' => ''];
        try {
            require_once __DIR__ . '/services/CourseOutcomeSyncService.php';
            $syncRes = CourseOutcomeSyncService::getInstance()->syncMasterMatrixToOfferings($curr_sub_id, $mappings);

            if (!empty($syncRes['status'])) {
                $res['status'] = 1;
                $res['message'] = "Master Articulation Matrix saved successfully. Cascaded {$syncRes['offerings_updated']} cell update(s) to active class offerings.";
            } else {
                throw new Exception($syncRes['error'] ?? "Failed to save matrix");
            }
        } catch (Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("CurriculumSubject::saveMasterArticulationMatrix Error: " . $e->getMessage());
        }
        return $res;
    }
}
