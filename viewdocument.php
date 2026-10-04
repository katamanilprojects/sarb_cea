<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = $_SESSION['user'] ?? '';
$role = $_SESSION['role'] ?? '';

if (empty($user)) {
    header("Location: ./index.php");
    exit();
}

require_once __DIR__ . '/services/FeatureManager.php';
\FeatureManager::requireAccess('MOD_STUDENT_PROFILE');

require_once __DIR__ . '/dbcredentials.class.php';

$docId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($docId <= 0) {
    http_response_code(400);
    die("Invalid document identifier.");
}

$db = new \DBCredentials();
$conn = $db->getConnection();

$stmt = $conn->prepare("SELECT id, roll_no, doc_type, doc_title, file_name, file_path, file_size, mime_type FROM student_documents WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $docId);
$stmt->execute();
$doc = $stmt->get_result()->fetch_assoc();

if (!$doc) {
    http_response_code(404);
    die("Requested document does not exist.");
}

// Locate file on disk
$localPath = __DIR__ . '/' . ltrim($doc['file_path'], '/');
$filePath = null;

if (file_exists($localPath)) {
    $filePath = $localPath;
} else {
    // Check fallback in jntuaceastudents in case document was uploaded via student portal
    $altPath = dirname(__DIR__) . '/jntuaceastudents/' . ltrim($doc['file_path'], '/');
    if (file_exists($altPath)) {
        $filePath = $altPath;
    }
}

if (!$filePath || !is_file($filePath)) {
    http_response_code(404);
    die("Document file was not found on the server repository.");
}

$mime = !empty($doc['mime_type']) ? $doc['mime_type'] : 'application/octet-stream';
$filename = !empty($doc['file_name']) ? basename($doc['file_name']) : basename($filePath);

// Clean output buffer before streaming binary
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"');
header('Cache-Control: private, max-age=3600, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit();
