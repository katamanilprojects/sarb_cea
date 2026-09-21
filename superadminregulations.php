<?php
session_start();
if ($_SESSION['role'] !== 'superadmin') {
    header('Location: ./');
    exit();
}

$page_title = "Manage Regulations";
require_once("superadminheader.php");
require_once("superadmin.class.php");
$superadmin = new SuperAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_or_update') {
        $data = [
            'id' => $_POST['id'] ?? null,
            'regulation' => strtoupper($_POST['regulation']) ?? '',
            'prog_id' => $_POST['prog_id'] ?? ''
        ];
        $result = $superadmin->addOrUpdateRegulation($data);
        $msg = $result['message'] ?? $result['error'] ?? '';
    } elseif ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = $superadmin->deleteRegulation($id);
        $msg = $result['message'] ?? $result['error'] ?? '';
    }
}

$regulations = $superadmin->getAllRegulations()['data'] ?? [];
$programs = $superadmin->getAllPrograms()['data'] ?? [];
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

$page_action = 'superadminregulations.php';
require_once("views/manage_regulations.php");
require_once("superadminfooter.php");
