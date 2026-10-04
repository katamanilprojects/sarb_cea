<?php
/**
 * Download OBE & Assessment Analytics Dossier Handler
 * 
 * Streams or downloads the 9-page OBE & Assessment Analytics Dossier PDF:
 * obe_analysis_report_<course_id>_<timestamp>.pdf
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$is_auth = false;
$userRole = $_SESSION['role'] ?? '';
$userDeptId = $_SESSION['dept_id'] ?? null;
$userFacId = $_SESSION['facid'] ?? null;

if (!empty($_SESSION['facid']) || !empty($_SESSION['role'])) {
    $is_auth = true;
}

if (php_sapi_name() !== 'cli') {
    require_once __DIR__ . '/services/FeatureManager.php';
    \FeatureManager::requireAccess('MOD_OBE_ANALYSIS');
}

if (php_sapi_name() === 'cli') {
    $sub_id = !empty($argv[1]) ? intval($argv[1]) : 656;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['sub_id'])) {
        die("Invalid Request: Subject ID is required.");
    }
    if (empty($_POST['secretcode']) || empty($_SESSION['secretcode']) || $_POST['secretcode'] != $_SESSION['secretcode']) {
        if (!$is_auth) {
            die("Invalid Request: Secret code mismatch.");
        }
    }
    $sub_id = intval($_POST['sub_id']);
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['sub_id'])) {
    if (!$is_auth) {
        http_response_code(403);
        die("Access Denied: Authentication required.");
    }
    $sub_id = intval($_GET['sub_id']);
} else {
    die("Invalid Request.");
}

// Faculty access control: ensure faculty teaches this subject
if (!empty($userFacId) && ($_SESSION['role'] ?? '') === 'faculty') {
    require_once __DIR__ . '/faculty.class.php';
    $facultyObj = new Faculty();
    $facultySubjects = $facultyObj->getSubjectsByFacultyId($userFacId);
    $allowedSubIds = array_column($facultySubjects['data'] ?? [], 'id');
    if (!in_array($sub_id, $allowedSubIds)) {
        http_response_code(403);
        die("Access Denied: You are not assigned to this subject.");
    }
}

// Ensure SEE marks are added before allowing download
require_once __DIR__ . '/seeassessment.class.php';
$seeObj = new SEEAssessment();
if (!$seeObj->isSEEMarksSubmitted($sub_id)) {
    http_response_code(400);
    die("Access Denied: Semester End Examination (SEE) marks must be added to this course before the OBE Analytics Dossier can be downloaded.");
}

require_once __DIR__ . '/services/OBEAnalysisPDFService.php';

try {
    $obeService = new OBEAnalysisPDFService();
    $filepath = $obeService->generateOBEAnalysisReport($sub_id, null, [
        'facid' => $userFacId
    ]);

    if (!file_exists($filepath)) {
        throw new Exception("Generated OBE report file not found.");
    }

    $filename = basename($filepath);

    if (php_sapi_name() === 'cli') {
        echo "OBE Analysis Report generated successfully at: " . $filepath . "\n";
        exit(0);
    }

    $disposition = (!empty($_GET['view']) && $_GET['view'] == '1') ? 'inline' : 'attachment';

    header('Content-Type: application/pdf');
    header('Content-Disposition: ' . $disposition . '; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: max-age=0, must-revalidate');
    header('Pragma: public');

    readfile($filepath);

    if (file_exists($filepath)) {
        unlink($filepath);
    }
    exit(0);

} catch (Exception $e) {
    http_response_code(500);
    die("Error generating OBE Analysis Report: " . htmlspecialchars($e->getMessage()));
}
