<?php
// facseemarkscondensed.php
ob_start();
session_start();
$page_title = "SEE Condensed Marks Review";
require_once("faculty.class.php");
require_once("cia.class.php");
require_once("seeassessment.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$seeObj = new SEEAssessment();

if (empty($_GET['sub_id']) || empty($_GET['component_id'])) {
    header("Location: facseemarks.php");
    exit;
}

$sub_id = intval($_GET['sub_id']);
$component_id = intval($_GET['component_id']);
$subjectDetails = $facultyObj->getSubjectDetails($sub_id);
$reg = $ciaObj->getRegulationForSubject($sub_id);
$maxSee = (float)$ciaObj->getAcademicSetting('theory_see_max_marks', $reg, $sub_id, 70.0);
$q1Max = (float)$ciaObj->getAcademicSetting('theory_compulsory_q1_marks', $reg, $sub_id, 20.0);

$consolidated = $seeObj->consolidateSEEMarks($sub_id, 'SEE-Theory');
$isSubmitted = $seeObj->isSEEMarksSubmitted($sub_id);

// Handle final submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['commit_see_marks'])) {
    if (!empty($_POST['students']) && is_array($_POST['students'])) {
        $commitData = [];
        foreach ($_POST['students'] as $stuId => $data) {
            $commitData[] = [
                'student_id' => intval($stuId),
                'final_external' => floatval($data['final_external']),
                'max_marks' => floatval($data['max_marks']),
                'q1_marks' => floatval($data['q1_marks']),
                'choice_marks' => floatval($data['choice_marks'])
            ];
        }
        $res = $seeObj->saveConsolidatedSEEMarks($sub_id, $commitData, $_SESSION['facid'], 'DETAILED');
        if ($res['status'] == 1) {
            $_SESSION['succ'] = "Semester End Examination marks finalized and committed successfully.";
            header("Location: facseemarks.php?sub_id=$sub_id");
            exit;
        } else {
            $_SESSION['err'] = "Failed to commit marks: " . $res['err'];
        }
    } else {
        $_SESSION['err'] = "No student marks data received to commit.";
    }
}

require_once("facheader.php");
?>

<div class="container-fluid my-4 px-4">
    <div class="card shadow-sm">
        <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-calculator me-2"></i>Condensed Review: External Examination (Either/Or Choice Resolution)</h5>
            <span class="badge bg-primary fs-6">Max SEE Marks: <?= $maxSee; ?>.0</span>
        </div>
        <div class="card-header bg-light">
            <strong>Course:</strong> <?= htmlspecialchars($subjectDetails['data']['sub_fullname'] ?? ''); ?> (<?= htmlspecialchars($subjectDetails['data']['subcode'] ?? ''); ?>)
            | <strong>Status:</strong> <?= $isSubmitted ? '<span class="badge bg-success">Previously Submitted</span>' : '<span class="badge bg-warning text-dark">Draft / Pending Submission</span>'; ?>
        </div>
        <div class="card-body">
            <?php if (!empty($_SESSION['succ'])): ?><div class="alert alert-success alert-dismissible fade show"><?= $_SESSION['succ']; unset($_SESSION['succ']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if (!empty($_SESSION['err'])): ?><div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['err']; unset($_SESSION['err']); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="alert alert-info py-2 small">
                <i class="bi bi-info-circle me-1"></i>
                <strong>Autonomous Choice Resolution Rule:</strong><br>
                &bull; For each Either/Or choice: $\text{Choice Score} = \max\left(\sum Q_{\text{OptA}}, \sum Q_{\text{OptB}}\right)$ (capped at 10 Marks).<br>
                &bull; $\text{Final SEE Score} = \min\left(\text{Score}(Q1) + \sum \text{Score}(\text{Choice}), <?= $maxSee; ?>\right)$.
            </div>

            <form method="POST">
                <div class="table-responsive border" style="max-height: 600px;">
                    <table class="table table-bordered table-sm text-center align-middle mb-0">
                        <thead class="table-dark sticky-top">
                            <tr>
                                <th rowspan="2" style="min-width: 120px;" class="align-middle">Roll No</th>
                                <th rowspan="2" style="min-width: 170px;" class="align-middle text-start">Student Name</th>
                                <th rowspan="2" class="table-warning text-dark align-middle" style="min-width: 90px;">Q1 Compulsory<br>(Max <?= $q1Max; ?>M)</th>
                                <th colspan="5">Either/Or Choices (Max 10M Each)</th>
                                <th rowspan="2" class="table-info text-dark align-middle" style="min-width: 90px;">Choice Total<br>(Max 50M)</th>
                                <th rowspan="2" class="table-success text-dark align-middle" style="min-width: 90px;">Final SEE<br>(Max <?= $maxSee; ?>M)</th>
                            </tr>
                            <tr>
                                <th>2 or 3</th>
                                <th>4 or 5</th>
                                <th>6 or 7</th>
                                <th>8 or 9</th>
                                <th>10 or 11</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($consolidated)): ?>
                                <tr><td colspan="10" class="text-muted py-3">No student records found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($consolidated as $stuId => $row): ?>
                                    <tr>
                                        <td class="fw-bold bg-light"><?= htmlspecialchars($row['username']); ?></td>
                                        <td class="text-start bg-light"><?= htmlspecialchars($row['name']); ?></td>
                                        <td class="table-warning fw-bold"><?= $row['q1_marks']; ?></td>
                                        <?php for ($g = 1; $g <= 5; $g++): 
                                            $grp = $row['groups'][$g] ?? ['resolved' => 0, 'parent_a' => '', 'parent_b' => '', 'sum_a' => 0, 'sum_b' => 0];
                                        ?>
                                            <td>
                                                <strong class="text-primary"><?= $grp['resolved']; ?></strong><br>
                                                <span class="text-muted" style="font-size: 0.72rem;">
                                                    (Q<?= $grp['parent_a']; ?>:<?= $grp['sum_a']; ?> | Q<?= $grp['parent_b']; ?>:<?= $grp['sum_b']; ?>)
                                                </span>
                                            </td>
                                        <?php endfor; ?>
                                        <td class="table-info fw-bold"><?= $row['choice_marks']; ?></td>
                                        <td class="table-success fw-bold fs-6"><?= $row['final_external']; ?></td>
                                    </tr>
                                    <input type="hidden" name="students[<?= $stuId; ?>][final_external]" value="<?= $row['final_external']; ?>">
                                    <input type="hidden" name="students[<?= $stuId; ?>][max_marks]" value="<?= $row['max_marks']; ?>">
                                    <input type="hidden" name="students[<?= $stuId; ?>][q1_marks]" value="<?= $row['q1_marks']; ?>">
                                    <input type="hidden" name="students[<?= $stuId; ?>][choice_marks]" value="<?= $row['choice_marks']; ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-between mt-3">
                    <div>
                        <a href="facseemarksentry.php?sub_id=<?= $sub_id; ?>&component_id=<?= $component_id; ?>" class="btn btn-secondary me-2">
                            <i class="bi bi-pencil me-1"></i> Edit Detailed Marks Grid
                        </a>
                        <a href="facseemarks.php?sub_id=<?= $sub_id; ?>" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Back to Hub
                        </a>
                    </div>
                    <?php if (!empty($consolidated)): ?>
                        <button type="submit" name="commit_see_marks" class="btn btn-success px-4" onclick="return confirm('Are you sure you want to finalize and lock these Semester End Examination marks?');">
                            <i class="bi bi-check-circle me-1"></i> Submit & Finalize External Marks
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once("facfooter.php"); ?>
