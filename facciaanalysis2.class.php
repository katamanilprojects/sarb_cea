<?php
// facciaanalysis2.class.php - Unified Backward-Compatible CO Analysis Facade & AJAX Router
require_once("dbcredentials.class.php");
require_once("coattainment.class.php");
require_once("learninganalytics.class.php");

/**
 * COAnalysis Facade Class
 * Combines modular COAttainmentTrait and LearningAnalyticsTrait
 * for direct & indirect attainment, learning analytics, and AJAX reporting.
 */
class COAnalysis extends DBCredentials
{
    use COAttainmentTrait;
    use LearningAnalyticsTrait;

    public function __construct()
    {
        parent::__construct();
    }
}

// ================= AJAX Request Handler =================
if (isset($_GET['action']) && !empty($_GET['sub_id'])) {
    $analysisObj = new COAnalysis();

    // Sanitize inputs (support single integer or comma-separated integers, e.g. "101,102")
    $raw_sub_id = trim($_GET['sub_id']);
    if (!preg_match('/^[0-9]+(,[0-9]+)*$/', $raw_sub_id)) {
        echo json_encode(["error" => "Invalid Subject ID"]);
        exit;
    }
    $sub_id = (strpos($raw_sub_id, ',') !== false) ? $raw_sub_id : (int)$raw_sub_id;

    // Handle 'all' assessment case explicitly
    $assessment_number_raw = $_GET['assessment_number'] ?? null;
    $assessment_number = ($assessment_number_raw === 'all' || $assessment_number_raw === null)
        ? null
        : filter_var($assessment_number_raw, FILTER_VALIDATE_INT);

    if ($assessment_number_raw !== null && $assessment_number_raw !== 'all' && ($assessment_number === false || $assessment_number <= 0)) {
        echo json_encode(["error" => "Invalid Assessment Number"]);
        exit;
    }

    header('Content-Type: application/json');

    switch ($_GET['action']) {
        case 'co_attainment':
            echo $analysisObj->getCOAttainment($sub_id, $assessment_number);
            break;
        case 'po_attainment':
            echo $analysisObj->getPOAttainment($sub_id, $assessment_number);
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
        case 'co_po_matrix':
            echo $analysisObj->getCoPoMatrixData($sub_id, $assessment_number);
            break;
        case 'co_spread':
            echo $analysisObj->getCOVisualSummary($sub_id, $assessment_number);
            break;
        case 'question_difficulty':
            echo $analysisObj->getQuestionDifficultyDiscrimination($sub_id, $assessment_number);
            break;
        default:
            echo json_encode(["error" => "Invalid action specified"]);
            break;
    }
    exit;
}
