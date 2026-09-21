<?php
require_once("dbcredentials.class.php");

class COAnalysis extends DBCredentials
{
    public function __construct()
    {
        parent::__construct(); // Initialize DB connection
    }

    /**
     * Fetch Course Outcome (CO) Attainment Analysis
     */
    public function getCOAttainment($subject_id, $assessment_id = null, $component_id = null)
    {

        $query = "
    SELECT c.co_number, CONCAT('CO', c.co_number) AS co_label,
        ROUND((COALESCE(SUM(m.marks_obtained), 0) / COALESCE(SUM(q.marks), 1)) * 100, 2) AS co_attainment_percentage
    FROM course_outcomes c
    LEFT JOIN question_co_mapping qcm ON qcm.co_id = c.id
    LEFT JOIN assessment_questions q ON qcm.question_id = q.id
    LEFT JOIN student_marks m ON q.id = m.question_id
    LEFT JOIN assessment_components ac ON q.component_id = ac.id 
    LEFT JOIN internal_assessments i ON ac.assessment_id = i.id 
    WHERE c.sub_id = ?
        ";

        $params = [$subject_id];

        if ($assessment_id) {
            $query .= " AND i.assessment_number = ?";
            $params[] = $assessment_id;
        }

        if ($component_id) {
            $query .= " AND q.component_id = ?";
            $params[] = $component_id;
        }

        $query .= ' GROUP BY c.co_number ORDER BY c.co_number';

        return $this->fetchResults($query, $params);
    }

    /**
     * Fetch Bloom's Taxonomy Performance Analysis
     */
    public function getBloomsPerformance($subject_id, $assessment_id = null, $component_id = null)
    {

        $query = "
            SELECT b.blooms_level, b.blooms_label,
            COALESCE(ROUND((SUM(CASE WHEN i.sub_id = ? THEN m.marks_obtained ELSE 0 END) /
                            SUM(CASE WHEN i.sub_id = ? THEN q.marks ELSE 0 END)) * 100, 2), 0) AS attainment_percentage
            FROM blooms_levels b
            LEFT JOIN assessment_questions q ON q.blooms_level_id = b.id
            LEFT JOIN student_marks m ON q.id = m.question_id
            LEFT JOIN assessment_components ac ON q.component_id = ac.id 
            LEFT JOIN internal_assessments i ON ac.assessment_id = i.id 
            WHERE i.sub_id = ?
            ";

        $params = [$subject_id, $subject_id, $subject_id];

        if ($assessment_id) {
            $query .= " AND i.assessment_number = ?";
            $params[] = $assessment_id;
        }

        if ($component_id) {
            $query .= " AND q.component_id = ?";
            $params[] = $component_id;
        }
        $query .= ' GROUP BY b.blooms_level ORDER BY b.blooms_level;';

        return $this->fetchResults($query, $params);
    }

    /**
     * Fetch Assessment Performance Analysis
     */
    public function getAssessmentPerformance($subject_id, $assessment_id = null, $component_id = null)
    {
        $query = "
        SELECT CONCAT('CIA - ', i.assessment_number) AS assessment_number, 
        ROUND((SUM(m.marks_obtained) / SUM(q.marks)) * 100, 2) AS attainment_percentage
        FROM student_marks m
        JOIN assessment_questions q ON m.question_id = q.id
        JOIN assessment_components ac ON q.component_id = ac.id 
        JOIN internal_assessments i ON ac.assessment_id = i.id 
        WHERE i.sub_id = ?
        ";

        $params = [$subject_id];

        if ($assessment_id) {
            $query .= " AND i.assessment_number = ?";
            $params[] = $assessment_id;
        }

        if ($component_id) {
            $query .= " AND q.component_id = ?";
            $params[] = $component_id;
        }

        $query .= " GROUP BY i.assessment_number ORDER BY i.assessment_number";

        return $this->fetchResults($query, $params);
    }

    public function getComponentPerformance($subject_id, $assessment_id = null)
    {
        $query = "
        SELECT ac.component_type,
        ROUND((SUM(m.marks_obtained) / SUM(q.marks)) * 100, 2) AS attainment_percentage
        FROM assessment_components ac
        JOIN assessment_questions q ON ac.id = q.component_id
        JOIN student_marks m ON q.id = m.question_id
        JOIN internal_assessments i ON ac.assessment_id = i.id 
        WHERE i.sub_id = ?
        ";

        $params = [$subject_id];

        if ($assessment_id) {
            $query .= " AND i.assessment_number = ?";
            $params[] = $assessment_id;
        }

        $query .= " GROUP BY ac.component_type ORDER BY FIELD(ac.component_type, 'Subjective', 'Objective', 'Assignment')";

        return $this->fetchResults($query, $params);
    }

    /**
     * Execute the query with prepared statements
     */
    private function fetchResults($query, $params)
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            $this->logs->errLog("SQL Prepare Error: " . $conn->error);
            return json_encode(["error" => "Database query preparation failed"]);
        }

        $stmt->bind_param(str_repeat('i', count($params)), ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            $this->logs->errLog("SQL Execution Error: " . $stmt->error);
            return json_encode(["error" => "Database query execution failed"]);
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        return json_encode($data);
    }
}


// Handle AJAX Requests
if (isset($_GET['action']) && !empty($_GET['sub_id'])) {
    $analysisObj = new COAnalysis();

    $sub_id = $_GET['sub_id'];
    $assessment_number = $_GET['assessment_number'] ?? null;

    switch ($_GET['action']) {
        case 'co_attainment':
            echo $analysisObj->getCOAttainment($sub_id, $assessment_number);
            break;
        case 'blooms_performance':
            echo $analysisObj->getBloomsPerformance($sub_id, $assessment_number);
            break;
        case 'assessment_performance':
            echo $analysisObj->getAssessmentPerformance($sub_id, $assessment_number);
            break;
        case 'component_performance':
            echo $analysisObj->getComponentPerformance($sub_id, $assessment_number);
            break;
        default:
            echo json_encode(["error" => "Invalid action"]);
    }
}
