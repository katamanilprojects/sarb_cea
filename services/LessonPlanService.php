<?php
namespace Services;

require_once __DIR__ . '/../dbcredentials.class.php';

class LessonPlanService extends \DBCredentials {
    private static ?self $instance = null;

    public function __construct() {
        parent::__construct();
    }

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Fetch complete lesson delivery plan for a subject offering
     */
    public function getPlanBySubject(int $sub_id): array {
        $stmt = $this->conn->prepare("
            SELECT lp.*, co.co_number, co.co_description 
            FROM lesson_plans lp
            JOIN course_outcomes co ON lp.co_id = co.id
            WHERE lp.sub_id = ?
            ORDER BY lp.lecture_number ASC
        ");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Add or update an individual lecture in the lesson plan
     */
    public function addOrUpdateLecturePlan(array $data): array {
        $res = ['status' => 0];
        try {
            $planned_hours = !empty($data['planned_hours']) ? (int)$data['planned_hours'] : 1;
            $bloom_level = !empty($data['bloom_level']) ? trim($data['bloom_level']) : 'L3-Apply';
            $pedagogy = !empty($data['pedagogy']) ? trim($data['pedagogy']) : 'Chalk & Talk';
            $ref = !empty($data['reference_material']) ? trim($data['reference_material']) : null;

            $stmt = $this->conn->prepare("
                INSERT INTO lesson_plans 
                    (sub_id, unit_number, lecture_number, planned_topic, co_id, bloom_level, pedagogy, reference_material, planned_hours)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    unit_number = VALUES(unit_number),
                    planned_topic = VALUES(planned_topic),
                    co_id = VALUES(co_id),
                    bloom_level = VALUES(bloom_level),
                    pedagogy = VALUES(pedagogy),
                    reference_material = VALUES(reference_material),
                    planned_hours = VALUES(planned_hours)
            ");
            $stmt->bind_param(
                "iiisisssi",
                $data['sub_id'],
                $data['unit_number'],
                $data['lecture_number'],
                $data['planned_topic'],
                $data['co_id'],
                $bloom_level,
                $pedagogy,
                $ref,
                $planned_hours
            );

            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Lecture plan saved successfully.";
            } else {
                throw new \Exception($stmt->error);
            }
        } catch (\Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("LessonPlanService::addOrUpdateLecturePlan error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Delete a lecture plan entry
     */
    public function deleteLecturePlan(int $id, int $sub_id): array {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("DELETE FROM lesson_plans WHERE id = ? AND sub_id = ?");
            $stmt->bind_param("ii", $id, $sub_id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Lecture plan deleted successfully.";
            } else {
                throw new \Exception($stmt->error);
            }
        } catch (\Exception $e) {
            $res['error'] = $e->getMessage();
            $this->logs->errLog("LessonPlanService::deleteLecturePlan error: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Retrieve next suggested lecture based on count of conducted attendance periods
     */
    public function getNextSuggestedLecture(int $sub_id): ?array {
        try {
            // Count distinct date+hour or total conducted periods for this subject
            $stmtCount = $this->conn->prepare("SELECT COUNT(DISTINCT date, hour) as conducted_count FROM diary WHERE sub_id = ?");
            $stmtCount->bind_param("i", $sub_id);
            $stmtCount->execute();
            $conducted = (int)($stmtCount->get_result()->fetch_assoc()['conducted_count'] ?? 0);
            $stmtCount->close();

            $nextLectureNum = $conducted + 1;
            $stmt = $this->conn->prepare("
                SELECT lp.*, co.co_number, co.co_description 
                FROM lesson_plans lp
                JOIN course_outcomes co ON lp.co_id = co.id
                WHERE lp.sub_id = ? AND lp.lecture_number = ?
                LIMIT 1
            ");
            $stmt->bind_param("ii", $sub_id, $nextLectureNum);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            return $res ?: null;
        } catch (\Exception $e) {
            $this->logs->errLog("LessonPlanService::getNextSuggestedLecture error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Compute syllabus progress & plan vs actual variance for NBA Criterion 2
     */
    public function getSyllabusVariance(int $sub_id): array {
        $plan = $this->getPlanBySubject($sub_id);
        $totalPlanned = count($plan);

        $stmtActual = $this->conn->prepare("
            SELECT COUNT(DISTINCT date, hour) as actual_conducted,
                   COUNT(DISTINCT co_addressed) as cos_covered
            FROM diary
            WHERE sub_id = ?
        ");
        $stmtActual->bind_param("i", $sub_id);
        $stmtActual->execute();
        $actualStats = $stmtActual->get_result()->fetch_assoc();
        $stmtActual->close();

        $actualConducted = (int)($actualStats['actual_conducted'] ?? 0);
        $pctCompleted = ($totalPlanned > 0) ? round(($actualConducted / $totalPlanned) * 100, 1) : 0.0;

        return [
            'total_planned_lectures' => $totalPlanned,
            'actual_conducted_lectures' => $actualConducted,
            'completion_percentage' => $pctCompleted,
            'cos_covered_count' => (int)($actualStats['cos_covered'] ?? 0),
            'status' => ($actualConducted >= $totalPlanned && $totalPlanned > 0) ? 'COMPLETED' : 'IN_PROGRESS'
        ];
    }
}
