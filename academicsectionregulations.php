<?php
session_start();
$page_title = "Manage Regulations";
require_once("academicsectionheader.php");
require_once("regulations.class.php");
require_once("programs.class.php");

$regulationsObj = new Regulations();
$programsObj = new Programs();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    $msg = "Permission denied. You are not authorized to perform this action.";
    /*
    if ($action === 'add_or_update') {
        $data = [
            'id' => $_POST['id'] ?? null,
            'regulation' => strtoupper($_POST['regulation']) ?? '',
            'prog_id' => $_POST['prog_id'] ?? ''
        ];
        $result = $regulationsObj->addOrUpdateRegulation($data);
        $msg = $result['message'] ?? $result['error'] ?? '';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $regulationsObj->deleteRegulation($id);
        $msg = $result['message'] ?? $result['error'] ?? '';
    }
    */
}

$regulations = $regulationsObj->getAllRegulations()['data'] ?? [];
$programs = $programsObj->getAllPrograms()['data'] ?? [];
$edit_regulation = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    foreach ($regulations as $regulation) {
        if ($regulation['id'] == $edit_id) {
            $edit_regulation = $regulation;
            break;
        }
    }
}

$page_action = 'academicsectionregulations.php';
require_once("views/manage_regulations.php");
require_once("academicsectionfooter.php");
