<?php
declare(strict_types=1);

namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../logs.class.php';

/**
 * Class ExamResultsService
 *
 * Handles examination results notification publishing, bulk CSV importation,
 * student grade card generation, and 1-click automated SEE marks ingestion for OBE.
 */
class ExamResultsService extends \DBCredentials
{
    private static ?self $instance = null;

    public function __construct()
    {
        parent::__construct();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ==========================================
    // 1. Examination Notifications
    // ==========================================

    /**
     * Get all exam notifications with optional filters.
     */
    public function getNotifications(?int $programId = null, ?int $regId = null, bool $onlyPublished = false): array
    {
        $sql = "SELECT en.*, p.prog_shortname, p.prog_fullname, r.regulation,
                       (SELECT COUNT(*) FROM exam_results er WHERE er.notification_id = en.id) AS results_count,
                       (SELECT COUNT(DISTINCT er.htno) FROM exam_results er WHERE er.notification_id = en.id) AS students_count
                FROM exam_notifications en
                LEFT JOIN programs p ON en.program_id = p.id
                LEFT JOIN regulations r ON en.regulation_id = r.id
                WHERE 1=1";

        $params = [];
        $types = "";

        if ($programId !== null && $programId > 0) {
            $sql .= " AND en.program_id = ?";
            $params[] = $programId;
            $types .= "i";
        }
        if ($regId !== null && $regId > 0) {
            $sql .= " AND en.regulation_id = ?";
            $params[] = $regId;
            $types .= "i";
        }
        if ($onlyPublished) {
            $sql .= " AND en.is_published = 1";
        }

        $sql .= " ORDER BY en.release_date DESC, en.id DESC";

        $stmt = $this->conn->prepare($sql);
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * Get single notification.
     */
    public function getNotificationById(int $id): ?array
    {
        $stmt = $this->conn->prepare("
            SELECT en.*, p.prog_shortname, p.prog_fullname, r.regulation 
            FROM exam_notifications en
            LEFT JOIN programs p ON en.program_id = p.id
            LEFT JOIN regulations r ON en.regulation_id = r.id
            WHERE en.id = ? LIMIT 1
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc() ?: null;
    }

    /**
     * Create an examination notification.
     */
    public function createNotification(array $data, int $userId): array
    {
        $code = strtoupper(trim($data['notification_code'] ?? ''));
        $title = trim($data['title'] ?? '');
        $acadYear = trim($data['academic_year'] ?? '');
        $progId = (int)($data['program_id'] ?? 0);
        $regId = (int)($data['regulation_id'] ?? 0);
        $yearSem = trim($data['yearsem'] ?? '');
        $monthYear = trim($data['month_year'] ?? '');
        $releaseDate = !empty($data['release_date']) ? $data['release_date'] : date('Y-m-d');
        $isPublished = isset($data['is_published']) ? (int)$data['is_published'] : 1;

        if (empty($code) || empty($title) || empty($acadYear) || $progId <= 0 || $regId <= 0 || empty($yearSem)) {
            return ['status' => 0, 'err' => 'Notification code, title, academic year, program, regulation, and year-sem are required.'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO exam_notifications (notification_code, title, academic_year, program_id, regulation_id, yearsem, month_year, release_date, is_published, created_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("sssiisssii", $code, $title, $acadYear, $progId, $regId, $yearSem, $monthYear, $releaseDate, $isPublished, $userId);

        if ($stmt->execute()) {
            $notifId = $this->conn->insert_id;
            $this->logs->actLog($userId, "CREATE_EXAM_NOTIFICATION", "Created exam notification: $title (Code: $code)");
            $this->dbActivityLog($userId, "CREATE_EXAM_NOTIFICATION", "Created exam notification: $title (Code: $code)", "admin", "EXAM_NOTIFICATION", (string)$notifId);
            return ['status' => 1, 'id' => $notifId, 'notification_id' => $notifId, 'msg' => 'Exam notification created successfully.'];
        }

        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to create exam notification.'];
    }

    /**
     * Toggle notification publish state.
     */
    public function togglePublishStatus(int $id, int $status, int $userId): array
    {
        $stmt = $this->conn->prepare("UPDATE exam_notifications SET is_published = ? WHERE id = ?");
        $stmt->bind_param("ii", $status, $id);
        if ($stmt->execute()) {
            $word = $status ? "published" : "hidden";
            $this->logs->actLog($userId, "TOGGLE_EXAM_NOTIFICATION", "Notification ID $id $word");
            $this->dbActivityLog($userId, "TOGGLE_EXAM_NOTIFICATION", "Notification ID $id $word", "admin", "EXAM_NOTIFICATION", (string)$id);
            return ['status' => 1, 'msg' => "Notification successfully $word."];
        }
        return ['status' => 0, 'err' => $this->conn->error ?: 'Failed to update status.'];
    }

    // ==========================================
    // 2. Results CSV Bulk Import
    // ==========================================

    /**
     * Import results from CSV file for a notification.
     * Expects CSV headers: HTNO, SubCode, SubName, Internals, Externals, Total, Grade, GradePoints, Credits, Status
     */
    public function importResultsCsv(int $notificationId, string $csvFilePath, int $userId): array
    {
        if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
            return ['status' => 0, 'err' => 'Cannot read uploaded CSV file.'];
        }

        $handle = fopen($csvFilePath, 'r');
        if (!$handle) {
            return ['status' => 0, 'err' => 'Failed to open CSV file stream.'];
        }

        // Read header row and sanitize BOM
        $header = fgetcsv($handle);
        if (!$header) {
            fclose($handle);
            return ['status' => 0, 'err' => 'CSV file is empty.'];
        }

        // Remove UTF-8 BOM
        $header[0] = preg_replace('/[\xEF\xBB\xBF]/', '', $header[0]);
        $normHeader = array_map(function($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', (string)$h)));
        }, $header);

        // Map column indices
        $colHtno = array_search('htno', $normHeader, true);
        if ($colHtno === false) $colHtno = array_search('rollno', $normHeader, true);
        if ($colHtno === false) $colHtno = array_search('hallticket', $normHeader, true);

        $colSubCode = array_search('subcode', $normHeader, true);
        if ($colSubCode === false) $colSubCode = array_search('subjectcode', $normHeader, true);

        $colSubName = array_search('subname', $normHeader, true);
        if ($colSubName === false) $colSubName = array_search('subjectname', $normHeader, true);

        $colInternal = array_search('internals', $normHeader, true);
        if ($colInternal === false) $colInternal = array_search('internalmarks', $normHeader, true);
        if ($colInternal === false) $colInternal = array_search('internal', $normHeader, true);

        $colExternal = array_search('externals', $normHeader, true);
        if ($colExternal === false) $colExternal = array_search('externalmarks', $normHeader, true);
        if ($colExternal === false) $colExternal = array_search('external', $normHeader, true);

        $colTotal = array_search('total', $normHeader, true);
        if ($colTotal === false) $colTotal = array_search('totalmarks', $normHeader, true);

        $colGrade = array_search('grade', $normHeader, true);
        if ($colGrade === false) $colGrade = array_search('gradeletter', $normHeader, true);

        $colPoints = array_search('gradepoints', $normHeader, true);
        if ($colPoints === false) $colPoints = array_search('points', $normHeader, true);

        $colCredits = array_search('credits', $normHeader, true);
        $colStatus = array_search('status', $normHeader, true);

        if ($colHtno === false || $colSubCode === false) {
            fclose($handle);
            return [
                'status' => 0, 
                'err' => 'CSV must include at least "HTNO" and "SubCode" columns. Found columns: ' . implode(', ', $header)
            ];
        }

        // Cache students by roll number
        $studentCache = [];
        $stRes = $this->conn->query("SELECT id, username FROM students");
        while ($r = $stRes->fetch_assoc()) {
            $studentCache[strtoupper(trim($r['username']))] = (int)$r['id'];
        }

        // Cache subjects by subcode
        $subjectCache = [];
        $subRes = $this->conn->query("SELECT id, subcode FROM subjects");
        while ($r = $subRes->fetch_assoc()) {
            $subjectCache[strtoupper(trim($r['subcode']))] = (int)$r['id'];
        }

        $stmt = $this->conn->prepare("
            INSERT INTO exam_results (
                notification_id, htno, student_id, subject_code, subject_name, subject_id,
                internal_marks, external_marks, total_marks, grade_letter, grade_points,
                credits_registered, credits_earned, result_status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                student_id = VALUES(student_id),
                subject_name = VALUES(subject_name),
                subject_id = VALUES(subject_id),
                internal_marks = VALUES(internal_marks),
                external_marks = VALUES(external_marks),
                total_marks = VALUES(total_marks),
                grade_letter = VALUES(grade_letter),
                grade_points = VALUES(grade_points),
                credits_registered = VALUES(credits_registered),
                credits_earned = VALUES(credits_earned),
                result_status = VALUES(result_status),
                imported_at = CURRENT_TIMESTAMP
        ");

        $imported = 0;
        $rowNum = 1;
        $errors = [];

        while (($data = fgetcsv($handle)) !== false) {
            $rowNum++;
            $htno = strtoupper(trim((string)($data[$colHtno] ?? '')));
            $subCode = strtoupper(trim((string)($data[$colSubCode] ?? '')));

            if (empty($htno) || empty($subCode)) {
                continue;
            }

            $subName = ($colSubName !== false && isset($data[$colSubName])) ? trim($data[$colSubName]) : '';
            $internal = ($colInternal !== false && is_numeric($data[$colInternal])) ? (float)$data[$colInternal] : null;
            $external = ($colExternal !== false && is_numeric($data[$colExternal])) ? (float)$data[$colExternal] : null;
            $total = ($colTotal !== false && is_numeric($data[$colTotal])) ? (float)$data[$colTotal] : (($internal !== null || $external !== null) ? (float)($internal + $external) : null);
            $grade = ($colGrade !== false && isset($data[$colGrade])) ? strtoupper(trim($data[$colGrade])) : 'F';
            $points = ($colPoints !== false && is_numeric($data[$colPoints])) 
                ? (int)$data[$colPoints] 
                : $this->getGradePoints($grade);
            $credits = ($colCredits !== false && is_numeric($data[$colCredits])) ? (float)$data[$colCredits] : 3.00;
            
            $resStatus = ($colStatus !== false && isset($data[$colStatus])) ? strtoupper(trim($data[$colStatus])) : '';
            if (!in_array($resStatus, ['PASS', 'FAIL', 'ABSENT', 'WITHHELD'], true)) {
                $resStatus = ($grade === 'F' || $grade === 'AB') ? 'FAIL' : 'PASS';
            }

            $creditsEarned = ($resStatus === 'PASS') ? $credits : 0.00;
            $studentId = $studentCache[$htno] ?? null;
            $subjectId = $subjectCache[$subCode] ?? null;

            $stmt->bind_param(
                "isissidddsidds",
                $notificationId, $htno, $studentId, $subCode, $subName, $subjectId,
                $internal, $external, $total, $grade, $points,
                $credits, $creditsEarned, $resStatus
            );

            if ($stmt->execute()) {
                $imported++;
            } else {
                $errors[] = "Row $rowNum ($htno - $subCode): " . $stmt->error;
            }
        }

        fclose($handle);

        $this->logs->actLog($userId, "IMPORT_EXAM_RESULTS", "Imported $imported results for notification ID: $notificationId");
        $this->dbActivityLog($userId, "IMPORT_EXAM_RESULTS", "Imported $imported results for notification ID: $notificationId", "admin", "EXAM_RESULTS", (string)$notificationId);

        return [
            'status' => 1,
            'imported' => $imported,
            'errors' => $errors,
            'msg' => "Successfully imported and published $imported student result records."
        ];
    }

    // ==========================================
    // 3. Automated SEE Marks Ingestion
    // ==========================================

    /**
     * Auto-sync published exam results into external_assessment_marks for a given subject.
     */
    public function syncResultsToSeeMarks(int $subjectId, int $userId): array
    {
        // 1. Get subject details
        $subStmt = $this->conn->prepare("SELECT id, subcode, sub_fullname, class_id FROM subjects WHERE id = ? LIMIT 1");
        $subStmt->bind_param("i", $subjectId);
        $subStmt->execute();
        $subject = $subStmt->get_result()->fetch_assoc();

        if (!$subject) {
            return ['status' => 0, 'err' => 'Subject not found.'];
        }

        $subCode = strtoupper(trim($subject['subcode']));

        // 2. Fetch all matching results for students enrolled in this subject via htno and student_sub
        $sql = "
            SELECT ss.stu_id AS student_id, er.external_marks, er.total_marks, er.grade_letter, er.grade_points
            FROM exam_results er
            JOIN students s ON er.htno = s.username
            JOIN student_sub ss ON s.id = ss.stu_id AND ss.sub_id = ?
            JOIN exam_notifications en ON er.notification_id = en.id
            WHERE er.subject_code = ? AND er.external_marks IS NOT NULL AND en.is_published = 1
            ORDER BY er.imported_at DESC
        ";
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param("is", $subjectId, $subCode);
        $stmt->execute();
        $res = $stmt->get_result();

        $rows = [];
        while ($r = $res->fetch_assoc()) {
            if (!empty($r['student_id'])) {
                // Keep latest result per student
                if (!isset($rows[$r['student_id']])) {
                    $rows[$r['student_id']] = $r;
                }
            }
        }

        if (empty($rows)) {
            return [
                'status' => 0, 
                'err' => "No published exam results found matching subject code '$subCode' for students enrolled in this class."
            ];
        }

        // 3. Insert or update external_assessment_marks
        $seeStmt = $this->conn->prepare("
            INSERT INTO external_assessment_marks 
                (student_id, subject_id, entry_mode, external_marks, max_marks, submitted_by)
            VALUES (?, ?, 'DIRECT', ?, 70.00, ?)
            ON DUPLICATE KEY UPDATE 
                entry_mode = 'DIRECT',
                external_marks = VALUES(external_marks),
                max_marks = VALUES(max_marks),
                q1_marks = NULL,
                choice_marks = NULL,
                submitted_by = VALUES(submitted_by),
                submitted_at = CURRENT_TIMESTAMP
        ");

        $syncedCount = 0;
        foreach ($rows as $studentId => $r) {
            $extMarks = (float)$r['external_marks'];
            $seeStmt->bind_param("iidi", $studentId, $subjectId, $extMarks, $userId);
            if ($seeStmt->execute()) {
                $syncedCount++;
            }
        }

        $this->logs->actLog($userId, "AUTO_SYNC_SEE_MARKS", "Auto-synced $syncedCount SEE marks for subject ID $subjectId from published results");
        $this->dbActivityLog($userId, "AUTO_SYNC_SEE_MARKS", "Auto-synced $syncedCount SEE marks for subject ID $subjectId from published results", "faculty", "SEE_MARKS", (string)$subjectId);

        return [
            'status' => 1,
            'synced_count' => $syncedCount,
            'msg' => "Successfully auto-synced external marks for $syncedCount students directly from verified examination results!"
        ];
    }

    // ==========================================
    // 4. Student Results Lookup & Grade Card
    // ==========================================

    /**
     * Fetch published results for a student roll number.
     */
    public function getStudentResults(string $htno, ?int $notificationId = null): array
    {
        $htno = strtoupper(trim($htno));
        $sql = "
            SELECT er.*, en.title AS notification_title, en.month_year, en.release_date,
                   en.yearsem, en.academic_year, r.regulation
            FROM exam_results er
            JOIN exam_notifications en ON er.notification_id = en.id
            JOIN regulations r ON en.regulation_id = r.id
            WHERE er.htno = ? AND en.is_published = 1
        ";
        $params = [$htno];
        $types = "s";

        if ($notificationId !== null && $notificationId > 0) {
            $sql .= " AND er.notification_id = ?";
            $params[] = $notificationId;
            $types .= "i";
        }

        $sql .= " ORDER BY en.release_date DESC, er.subject_code ASC";

        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $res = $stmt->get_result();

        $results = [];
        $totalCreditsRegistered = 0.0;
        $totalCreditsEarned = 0.0;
        $totalWeightedPoints = 0.0;

        while ($row = $res->fetch_assoc()) {
            $results[] = $row;
            $credits = (float)$row['credits_registered'];
            $earned = (float)$row['credits_earned'];
            $points = (int)$row['grade_points'];

            $totalCreditsRegistered += $credits;
            $totalCreditsEarned += $earned;
            $totalWeightedPoints += ($credits * $points);
        }

        $sgpa = ($totalCreditsRegistered > 0) ? round($totalWeightedPoints / $totalCreditsRegistered, 2) : 0.00;

        return [
            'htno' => $htno,
            'results' => $results,
            'total_registered' => $totalCreditsRegistered,
            'total_earned' => $totalCreditsEarned,
            'sgpa' => $sgpa
        ];
    }

    /**
     * Map letter grades to standard 10-point grade scale points.
     */
    public function getGradePoints(string $grade): int
    {
        return match(strtoupper(trim($grade))) {
            'O', 'S' => 10,
            'A+', 'EX' => 9,
            'A' => 8,
            'B+' => 7,
            'B' => 6,
            'C' => 5,
            'D', 'P' => 4,
            default => 0
        };
    }
}
