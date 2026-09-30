<?php
require_once("dbcredentials.class.php");

class Subject extends DBCredentials {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Standard SQL projection fields for the subjects table.
     * Aliases dynamically formatted strings to sub_fullname and sub_shortname
     * so legacy views receive the correct display name without code modification.
     */
    public static function sqlProjection($alias = 's') {
        return "
            {$alias}.id,
            {$alias}.class_id,
            {$alias}.curr_sub_id,
            {$alias}.subject_sno,
            {$alias}.subcode,
            {$alias}.sub_type,
            {$alias}.group_name,
            {$alias}.sub_fullname AS raw_sub_fullname,
            {$alias}.sub_shortname AS raw_sub_shortname,
            CASE
                WHEN {$alias}.group_name IS NOT NULL AND {$alias}.group_name != ''
                THEN CONCAT({$alias}.sub_fullname, ' (Group - ', {$alias}.group_name, ')')
                ELSE {$alias}.sub_fullname
            END AS sub_fullname,
            CASE
                WHEN {$alias}.group_name IS NOT NULL AND {$alias}.group_name != ''
                THEN CONCAT({$alias}.sub_shortname, ' (', {$alias}.group_name, ')')
                ELSE {$alias}.sub_shortname
            END AS sub_shortname
        ";
    }

    /**
     * Get all subjects for a class, ordered by S.No and group
     */
    public function getSubjectsByClass($classId) {
        $data = [];
        try {
            $stmt = $this->conn->prepare(
                "SELECT " . self::sqlProjection('s') . "
                 FROM subjects s
                 WHERE s.class_id = ?
                 ORDER BY CAST(s.subject_sno AS UNSIGNED) ASC, s.group_name ASC"
            );
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getSubjectsByClass - " . $e->getMessage());
        }
        return $data;
    }

    /**
     * Get subjects assigned to a specific faculty member
     * Includes class metadata (classname, acad_year, start_date, end_date) required by faculty views.
     */
    public function getSubjectsByFaculty($facultyId) {
        $data = [];
        try {
            $stmt = $this->conn->prepare(
                "SELECT " . self::sqlProjection('s') . ",
                        fs.id AS faculty_sub_id,
                        c.acad_year,
                        c.classname AS class_name,
                        c.start_date,
                        c.end_date
                 FROM subjects s
                 JOIN faculty_sub fs ON fs.sub_id = s.id
                 JOIN classes c ON s.class_id = c.id
                 WHERE fs.faculty_id = ?
                 ORDER BY c.start_date DESC, CAST(s.subject_sno AS UNSIGNED) ASC, s.group_name ASC"
            );
            $stmt->bind_param("i", $facultyId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getSubjectsByFaculty - " . $e->getMessage());
        }
        return $data;
    }

    /**
     * Get single subject details by primary key
     */
    public function getSubjectById($subjectId) {
        try {
            $stmt = $this->conn->prepare(
                "SELECT " . self::sqlProjection('s') . "
                 FROM subjects s WHERE s.id = ? LIMIT 1"
            );
            $stmt->bind_param("i", $subjectId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $row ?: null;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getSubjectById - " . $e->getMessage());
        }
        return null;
    }

    /**
     * Fetch available master curriculum subjects matching class regulation/branch/semester
     */
    public function getAvailableCurriculumForClass($classId) {
        $data = [];
        try {
            $stmt = $this->conn->prepare(
                "SELECT cs.*
                 FROM curriculum_subjects cs
                 JOIN classes c ON c.reg_id = cs.reg_id
                               AND c.spec_id = cs.spec_id
                               AND c.yearsem = cs.yearsem
                 WHERE c.id = ? AND cs.status = 1
                 ORDER BY cs.subject_sno ASC"
            );
            $stmt->bind_param("i", $classId);
            $stmt->execute();
            $data = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog("Subject::getAvailableCurriculumForClass - " . $e->getMessage());
        }
        return $data;
    }

    /**
     * Create/Provision a subject offering instance
     */
    public function createOffering($classId, $currSubId, $sno, $subcode, $shortname, $fullname, $subType, $groupName = '') {
        try {
            $currSubIdVal = !empty($currSubId) ? (int)$currSubId : null;
            $subcode      = strtoupper(trim($subcode));
            $groupName    = strtoupper(trim($groupName));

            $stmt = $this->conn->prepare(
                "INSERT INTO subjects
                 (class_id, curr_sub_id, subject_sno, subcode, sub_shortname, sub_fullname, sub_type, group_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->bind_param("iissssss",
                $classId, $currSubIdVal, $sno, $subcode, $shortname, $fullname, $subType, $groupName
            );
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::createOffering - " . $e->getMessage());
        }
        return false;
    }

    /**
     * Update an existing subject offering
     */
    public function updateOffering($subjectId, $currSubId, $sno, $subcode, $shortname, $fullname, $subType, $groupName = '') {
        try {
            $currSubIdVal = !empty($currSubId) ? (int)$currSubId : null;
            $subcode      = strtoupper(trim($subcode));
            $groupName    = strtoupper(trim($groupName));

            $stmt = $this->conn->prepare(
                "UPDATE subjects SET
                   curr_sub_id   = ?,
                   subject_sno   = ?,
                   subcode       = ?,
                   sub_shortname = ?,
                   sub_fullname  = ?,
                   sub_type      = ?,
                   group_name    = ?
                 WHERE id = ?"
            );
            $stmt->bind_param("issssssi",
                $currSubIdVal, $sno, $subcode, $shortname, $fullname, $subType, $groupName, $subjectId
            );
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::updateOffering - " . $e->getMessage());
        }
        return false;
    }

    /**
     * Delete subject offering
     */
    public function deleteOffering($subjectId) {
        try {
            $stmt = $this->conn->prepare("DELETE FROM subjects WHERE id = ?");
            $stmt->bind_param("i", $subjectId);
            $ok = $stmt->execute();
            $stmt->close();
            return $ok;
        } catch (Exception $e) {
            $this->logs->errLog("Subject::deleteOffering - " . $e->getMessage());
        }
        return false;
    }
}
?>
