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

            $sql = "SELECT id, subject_sno, subcode, sub_fullname, sub_shortname, sub_type, lecture_hours, tutorial_hours, practical_hours, credits
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
            $sql = "SELECT subject_sno, subcode, sub_fullname, sub_shortname, sub_type, lecture_hours, tutorial_hours, practical_hours, credits
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
            $sql2 = "SELECT subject_sno, subcode, sub_fullname, sub_shortname, sub_type, lecture_hours, tutorial_hours, practical_hours, credits
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
                    $row3['lecture_hours'] = 0.0;
                    $row3['tutorial_hours'] = 0.0;
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
            $lectureHours = (float)($data['lecture_hours'] ?? 0.0);
            $tutorialHours = (float)($data['tutorial_hours'] ?? 0.0);
            $practicalHours = (float)($data['practical_hours'] ?? 0.0);
            $credits = (float)($data['credits'] ?? 0.0);

            if (empty($progId) || empty($regId) || empty($specId) || empty($yearsem) || empty($subcode) || empty($subFullname) || empty($subShortname)) {
                $res['error'] = 'Please fill all required fields.';
                return $res;
            }

            if ($id > 0) {
                // Update
                $sql = "UPDATE curriculum_subjects 
                        SET subject_sno = ?, subcode = ?, sub_fullname = ?, sub_shortname = ?, sub_type = ?, 
                            lecture_hours = ?, tutorial_hours = ?, practical_hours = ?, credits = ?, status = 1
                        WHERE id = ?";
                $stmt = $this->conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $this->conn->error);
                }
                $stmt->bind_param("issssddddi", $subjectSno, $subcode, $subFullname, $subShortname, $subType, $lectureHours, $tutorialHours, $practicalHours, $credits, $id);
                if (!$stmt->execute()) {
                    throw new Exception("Execute failed: " . $stmt->error);
                }
                $stmt->close();
                $res['status'] = 1;
                $res['message'] = 'Curriculum subject updated successfully.';
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
                                  SET subject_sno = ?, sub_fullname = ?, sub_shortname = ?, sub_type = ?, 
                                      lecture_hours = ?, tutorial_hours = ?, practical_hours = ?, credits = ?, status = 1
                                  WHERE id = ?";
                    $uStmt = $this->conn->prepare($updateSql);
                    $uStmt->bind_param("isssddddi", $subjectSno, $subFullname, $subShortname, $subType, $lectureHours, $tutorialHours, $practicalHours, $credits, $existingId);
                    $uStmt->execute();
                    $uStmt->close();
                    $res['status'] = 1;
                    $res['message'] = 'Curriculum subject already existed for this course and was updated successfully.';
                } else {
                    $checkStmt->close();
                    // Insert
                    $sql = "INSERT INTO curriculum_subjects 
                            (prog_id, reg_id, spec_id, yearsem, subject_sno, subcode, sub_fullname, sub_shortname, sub_type, lecture_hours, tutorial_hours, practical_hours, credits, status)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
                    $stmt = $this->conn->prepare($sql);
                    if (!$stmt) {
                        throw new Exception("Prepare failed: " . $this->conn->error);
                    }
                    $stmt->bind_param("iiisissssdddd", $progId, $regId, $specId, $yearsem, $subjectSno, $subcode, $subFullname, $subShortname, $subType, $lectureHours, $tutorialHours, $practicalHours, $credits);
                    if (!$stmt->execute()) {
                        throw new Exception("Execute failed: " . $stmt->error);
                    }
                    $stmt->close();
                    $res['status'] = 1;
                    $res['message'] = 'Curriculum subject added successfully.';
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
}
