<?php
session_start();
header('Content-Type: application/json');

if (empty($_SESSION['role']) || !in_array($_SESSION['role'], ['academic_section', 'admin', 'superadmin', 'hod'])) {
    echo json_encode(['status' => 0, 'error' => 'Unauthorized access.']);
    exit();
}

require_once("curriculum_subject.class.php");
$obj = new CurriculumSubject();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'lookup_code') {
    $subcode = $_GET['subcode'] ?? ($_POST['subcode'] ?? '');
    $regId = (int)($_GET['reg_id'] ?? ($_POST['reg_id'] ?? 0));

    if (empty($subcode)) {
        echo json_encode(['status' => 0, 'error' => 'Subject code is required.']);
        exit();
    }

    $res = $obj->lookupSubjectByCodeAndReg($subcode, $regId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_regulations') {
    $progId = (int)($_GET['prog_id'] ?? ($_POST['prog_id'] ?? 0));
    $res = $obj->getRegulationsByProgramId($progId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_specializations') {
    $progId = (int)($_GET['prog_id'] ?? ($_POST['prog_id'] ?? 0));
    $res = $obj->getSpecializationsByProgramId($progId);
    echo json_encode($res);
    exit();
}

if ($action === 'get_subjects_for_class') {
    $regId = (int)($_GET['reg_id'] ?? ($_POST['reg_id'] ?? 0));
    $specId = (int)($_GET['spec_id'] ?? ($_POST['spec_id'] ?? 0));
    $yearsem = trim($_GET['yearsem'] ?? ($_POST['yearsem'] ?? ''));

    $res = $obj->getSubjectsForClass($regId, $specId, $yearsem);
    echo json_encode($res);
    exit();
}

echo json_encode(['status' => 0, 'error' => 'Invalid action.']);
exit();
