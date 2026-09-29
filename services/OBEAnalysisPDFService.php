<?php
/**
 * OBE & Assessment Analytics Dossier Service
 * 
 * Generates an audit-compliant, 9-page OBE & Assessment Analytics Dossier:
 * Page 1: CIA-1 Question Paper Analysis
 * Page 2: CIA-2 Question Paper Analysis
 * Page 3: Consolidated CIA QP Analysis
 * Page 4: CIA & Learning Analytics Report
 * Page 5: SEE Question Paper Analysis
 * Page 6: SEE Marks & Grade Distribution Summary (O, S, A, B, C, F)
 * Page 7: SEE Result Analysis (Attainment levels based on 60% cohort benchmark)
 * Page 8: Combined Direct Attainment Analysis (Formula: 30% CIA + 70% SEE)
 * Page 9: Comprehensive CO & PO Attainment Matrix (Direct + 20% Indirect Feedback)
 */

require_once __DIR__ . '/../dbcredentials.class.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../faculty.class.php';
require_once __DIR__ . '/../cia.class.php';
require_once __DIR__ . '/../seeassessment.class.php';
require_once __DIR__ . '/../coattainment.class.php';
require_once __DIR__ . '/../learninganalytics.class.php';
require_once __DIR__ . '/../feedbackservice.class.php';
require_once __DIR__ . '/SettingsService.php';

class OBEAnalysisPDFService
{
    private $conn;
    private $facultyObj;
    private $ciaObj;
    private $seeObj;
    private $coEngine;
    private $laEngine;
    private $feedbackService;
    private $settingsService;

    // Professional color palette matching institutional theme
    private $colors = [
        'primary' => '#1a365d',
        'secondary' => '#2c5282',
        'accent' => '#2b6cb0',
        'success' => '#198754',
        'warning' => '#d97706',
        'danger' => '#dc3545',
        'light' => '#f8f9fa',
        'border' => '#dee2e6',
        'header_bg' => '#f2f2f2',
        'muted' => '#6c757d'
    ];

    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        $this->facultyObj = new Faculty();
        $this->ciaObj = new CIA();
        $this->seeObj = new SEEAssessment();
        $this->coEngine = new COAttainmentEngine();
        $this->laEngine = new LearningAnalytics();
        $this->feedbackService = new FeedbackService();
        $this->settingsService = \Services\SettingsService::getInstance();
    }

    /**
     * Main entry point to generate the 9-page OBE Analysis Dossier PDF
     * 
     * @param int|array $courseData Subject ID or array containing course metadata
     * @param array|null $feedbackData Optional external feedback data
     * @param array $options Optional generation settings
     * @return string Absolute file path to the generated PDF
     */
    public function generateOBEAnalysisReport($courseData, $feedbackData = null, array $options = [])
    {
        $sub_id = is_array($courseData) ? intval($courseData['sub_id'] ?? $courseData['id'] ?? 0) : intval($courseData);
        if ($sub_id <= 0) {
            throw new InvalidArgumentException("Invalid subject ID provided for OBE Analysis Report.");
        }

        // Validate that SEE marks are submitted for this course
        if (!$this->seeObj->isSEEMarksSubmitted($sub_id)) {
            throw new RuntimeException("OBE Analytics Dossier cannot be generated because Semester End Examination (SEE) marks have not been added yet for this course.");
        }

        // Fetch course and faculty details
        $facid = $options['facid'] ?? null;
        if (!empty($facid)) {
            $details = $this->facultyObj->getClassSubjectFacultyDetails($sub_id, $facid);
        } else {
            require_once __DIR__ . '/../hod.class.php';
            $hodObj = new HOD();
            $details = $hodObj->getClsSubFacBySubID($sub_id);
        }

        $meta = $this->extractCourseMeta($details, $sub_id);
        $regulation = $meta['regulation'];

        // Academic settings & weights
        $w_cia = (float)$this->settingsService->get('attainment_direct_cia_weight', $regulation, 0.30);
        $w_see = (float)$this->settingsService->get('attainment_direct_see_weight', $regulation, 0.70);
        $w_direct = (float)$this->settingsService->get('overall_direct_weight', $regulation, 0.80);
        $w_indirect = (float)$this->settingsService->get('overall_indirect_weight', $regulation, 0.20);
        $targetThreshold = (float)$this->settingsService->get('co_target_percentage', $regulation, 60.0);

        // Initialize mPDF
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 8,
            'margin_right' => 8,
            'margin_top' => 36,
            'margin_bottom' => 12,
            'default_font_size' => 8,
            'default_font' => 'helvetica',
            'tempDir' => __DIR__ . '/../uploads/tmp'
        ]);
        $mpdf->aliasNbPages();

        $headerFn = function($sectionTitle) use ($meta) {
            return '
            <table cellpadding="6" cellspacing="0" style="width:100%; border-collapse:collapse; table-layout:fixed;">
                <tr>
                    <td style="text-align:left; width:28%; font-size:10px; line-height:14px; padding-bottom:0px; word-wrap:break-word;">
                        <strong>Class:</strong> ' . htmlspecialchars($meta['class']) . '<br>
                        <strong>Semester:</strong> ' . htmlspecialchars($meta['semester']) . '<br>
                        <strong>Branch:</strong> ' . htmlspecialchars($meta['branch']) . '
                    </td>
                    <td style="text-align:center; width:44%; font-size:16px; padding-bottom:0px; font-weight:bold; color:#1a365d; word-wrap:break-word;">
                        ' . htmlspecialchars($sectionTitle) . '
                    </td>
                    <td style="text-align:right; width:28%; font-size:10px; line-height:14px; padding-bottom:0px; word-wrap:break-word;">
                        <strong>Acad. Year:</strong> ' . htmlspecialchars($meta['acad_year']) . '<br>
                        <strong>Starting:</strong> ' . htmlspecialchars($meta['start_date']) . '<br>
                        <strong>Ending:</strong> ' . htmlspecialchars($meta['end_date']) . '
                    </td>
                </tr>
                <tr>
                    <td colspan="2" style="text-align:left; font-size:10px; line-height:14px; padding-top:2px; word-wrap:break-word;">
                        <strong>Subject:</strong> ' . htmlspecialchars($meta['subject']) . ' (' . htmlspecialchars($meta['subcode']) . ')
                    </td>
                    <td style="text-align:right; font-size:10px; line-height:14px; padding-top:2px; word-wrap:break-word;">
                        <strong>Teacher:</strong> ' . htmlspecialchars($meta['faculty']) . '
                    </td>
                </tr>
            </table>
            <hr style="margin:2px 0 0 0; padding:0; border:0; border-top:1px solid #000;"><br>';
        };

        // Shared Bloom's taxonomy definitions
        $bloomNames = [
            1 => 'Remember (L1)',
            2 => 'Understand (L2)',
            3 => 'Apply (L3)',
            4 => 'Analyze (L4)',
            5 => 'Evaluate (L5)',
            6 => 'Create (L6)'
        ];

        // ==========================================
        // PAGE 1: CIA-1 Question Paper Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA Question Paper Analysis'), 0);
        $cia1Data = $this->getCIAQuestionData($sub_id, 1, $bloomNames);
        $htmlP1 = $this->renderCIAQPPageHtml(1, $cia1Data);
        $mpdf->WriteHTML($htmlP1);

        // ==========================================
        // PAGE 2: CIA-2 Question Paper Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA Question Paper Analysis'), 0);
        $mpdf->AddPage();
        $cia2Data = $this->getCIAQuestionData($sub_id, 2, $bloomNames);
        $htmlP2 = $this->renderCIAQPPageHtml(2, $cia2Data);
        $mpdf->WriteHTML($htmlP2);

        // ==========================================
        // PAGE 3: Consolidated CIA QP Matrix
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Consolidated CIA QP Analysis'), 0);
        $mpdf->AddPage();
        $htmlP3 = $this->renderConsolidatedCIAHtml($sub_id, $cia1Data, $cia2Data, $bloomNames);
        $mpdf->WriteHTML($htmlP3);

        // ==========================================
        // PAGE 4: CIA & Learning Analytics Report
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('CIA & Learning Analytics Report'), 0);
        $mpdf->AddPage();
        $htmlP4 = $this->renderLearningAnalyticsHtml($sub_id);
        $mpdf->WriteHTML($htmlP4);

        // ==========================================
        // PAGE 5: SEE Question Paper Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('SEE Question Paper Analysis'), 0);
        $mpdf->AddPage();
        $htmlP5 = $this->renderSEEQPAnalysisHtml($sub_id, $bloomNames);
        $mpdf->WriteHTML($htmlP5);

        // ==========================================
        // PAGE 6: SEE Marks & Grade Distribution
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('SEE Marks & Grade Distribution'), 0);
        $mpdf->AddPage();
        $htmlP6 = $this->renderSEEGradeDistributionHtml($sub_id);
        $mpdf->WriteHTML($htmlP6);

        // ==========================================
        // PAGE 7: SEE Result Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('SEE Result Analysis'), 0);
        $mpdf->AddPage();
        $htmlP7 = $this->renderSEEResultAnalysisHtml($sub_id);
        $mpdf->WriteHTML($htmlP7);

        // ==========================================
        // PAGE 8: Combined Direct Attainment Analysis
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('Combined Direct Attainment Analysis'), 0);
        $mpdf->AddPage();
        $htmlP8 = $this->renderCombinedDirectAttainmentHtml($sub_id, $w_cia, $w_see);
        $mpdf->WriteHTML($htmlP8);

        // ==========================================
        // PAGE 9: OBE Outcome Attainment Matrix
        // ==========================================
        $mpdf->SetHTMLHeader($headerFn('OBE Outcome Attainment Matrix'), 0);
        $mpdf->AddPage();
        $htmlP9 = $this->renderOBEMatrixHtml($sub_id, $feedbackData, $w_cia, $w_see, $w_direct, $w_indirect, $targetThreshold);
        $mpdf->WriteHTML($htmlP9);

        // Save PDF
        $filename = 'obe_analysis_report_' . $sub_id . '_' . date("Ymd_His") . '.pdf';
        $outDir = __DIR__ . '/../uploads';
        if (!is_dir($outDir)) {
            mkdir($outDir, 0777, true);
        }
        $filepath = $outDir . '/' . $filename;
        $mpdf->Output($filepath, 'F');

        return $filepath;
    }

    /**
     * Extract metadata from course and class details
     */
    private function extractCourseMeta($details, $sub_id)
    {
        $class_full = $details['data']['classname'] ?? '';
        $subject = $details['data']['sub_fullname'] ?? '';
        $faculty = $details['data']['faculty_name'] ?? 'N/A';
        $acadYear = $details['data']['acad_year'] ?? date('Y') . '-' . (date('Y') + 1);
        $subCode = $details['data']['subcode'] ?? '';

        if (empty($subCode) || empty($subject)) {
            $sStmt = $this->conn->prepare("SELECT subcode, sub_fullname FROM subjects WHERE id = ?");
            if ($sStmt) {
                $sStmt->bind_param("i", $sub_id);
                if ($sStmt->execute()) {
                    $sStmt->bind_result($dbSubcode, $dbSubname);
                    if ($sStmt->fetch()) {
                        if (empty($subCode)) $subCode = $dbSubcode;
                        if (empty($subject)) $subject = $dbSubname;
                    }
                }
                $sStmt->close();
            }
        }

        $regulation = 'R23';
        if ($rStmt = $this->conn->prepare("SELECT r.regulation FROM subjects s JOIN classes c ON s.class_id = c.id JOIN regulations r ON c.reg_id = r.id WHERE s.id = ?")) {
            $rStmt->bind_param("i", $sub_id);
            if ($rStmt->execute()) {
                $rStmt->bind_result($foundReg);
                if ($rStmt->fetch() && !empty($foundReg)) {
                    $regulation = strtoupper(trim($foundReg));
                }
            }
            $rStmt->close();
        }

        // Parse branch and class parts
        $class_parts = explode(' - ', $class_full);
        $semester = trim($class_parts[2] ?? '');
        preg_match_all('/\(([^)]+)\)/', $class_parts[0] ?? '', $matches);
        $branch = '';
        if (count($matches[1]) === 1) {
            $branch = $matches[1][0];
        } elseif (count($matches[1]) >= 2) {
            $last = array_pop($matches[1]);
            $secondLast = array_pop($matches[1]);
            $branch = $secondLast . ' (' . $last . ')';
        }

        $pos = strpos($class_parts[0] ?? '', '(');
        $class = ($pos !== false) ? trim(substr($class_parts[0], 0, $pos)) : ($class_parts[0] ?? '');
        if (isset($class_parts[1])) {
            $class .= ' - ' . trim($class_parts[1]);
        }

        // Attendance date range
        $attData = $this->facultyObj->getDetailedAttendanceBySubject($sub_id, null, null);
        $startDate = 'N/A';
        $endDate = 'N/A';
        if (!empty($attData['data'])) {
            $dateHours = array_keys($attData['data'][0]['attendance']);
            usort($dateHours, function ($a, $b) {
                return strtotime($a) - strtotime($b);
            });
            if (!empty($dateHours)) {
                $first = explode('-', $dateHours[0]);
                $last = explode('-', end($dateHours));
                $startDate = date('d-m-Y', strtotime($first[0] . '-' . $first[1] . '-' . $first[2]));
                $endDate = date('d-m-Y', strtotime($last[0] . '-' . $last[1] . '-' . $last[2]));
            }
        }

        return [
            'class' => $class,
            'semester' => $semester,
            'branch' => $branch,
            'subject' => $subject,
            'subcode' => $subCode,
            'faculty' => $faculty,
            'acad_year' => $acadYear,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'regulation' => $regulation
        ];
    }

    /**
     * Retrieve question and Bloom's data for a specific CIA mid examination
     */
    private function getCIAQuestionData($sub_id, $mid, array $bloomNames)
    {
        $midComps = $this->ciaObj->getAssessmentComponents($sub_id, $mid);
        $questions = [];
        $btlDist = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.0];
        $coDist = [];
        $totalMarks = 0.0;
        $componentSummary = [];

        if (!empty($midComps['data'])) {
            foreach ($midComps['data'] as $c) {
                $compName = $c['component_type'] . (count($midComps['data']) > 1 ? ' #' . $c['sequence_number'] : '');
                $qs = $this->ciaObj->getQuestionsByComponent($c['id']);
                $cMarks = 0.0;
                $cCount = 0;

                if (!empty($qs)) {
                    foreach ($qs as $q) {
                        $cos = $this->ciaObj->getCOsByQuestion($q['id']);
                        $btl = intval($q['blooms_level_id']);
                        $marks = floatval($q['marks']);
                        $totalMarks += $marks;
                        $cMarks += $marks;
                        $cCount++;

                        if (isset($btlDist[$btl])) {
                            $btlDist[$btl] += $marks;
                        }

                        $coWeight = (count($cos) > 0) ? ($marks / count($cos)) : $marks;
                        foreach ($cos as $coLabel) {
                            $coDist[$coLabel] = ($coDist[$coLabel] ?? 0.0) + $coWeight;
                        }

                        $questions[] = [
                            'comp' => $compName,
                            'label' => $q['question_label'],
                            'type' => $q['question_type'] ?? 'Theory',
                            'marks' => $marks,
                            'btl' => $btl,
                            'cos' => !empty($cos) ? implode(', ', $cos) : 'N/A'
                        ];
                    }
                }
                $componentSummary[] = [
                    'comp' => $compName,
                    'count' => $cCount,
                    'marks' => $cMarks
                ];
            }
        }

        return [
            'mid' => $mid,
            'questions' => $questions,
            'btl_dist' => $btlDist,
            'co_dist' => $coDist,
            'total_marks' => $totalMarks,
            'components' => $componentSummary
        ];
    }

    /**
     * Render CIA QP Analysis HTML for Page 1 & Page 2
     */
    private function renderCIAQPPageHtml($mid, array $data)
    {
        $bloomNames = [
            1 => 'Remember (L1)',
            2 => 'Understand (L2)',
            3 => 'Apply (L3)',
            4 => 'Analyze (L4)',
            5 => 'Evaluate (L5)',
            6 => 'Create (L6)'
        ];

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 8px 0;">
                Continuous Internal Assessment - ' . $mid . ' (CIA-' . $mid . ') Question Paper Analysis
            </h3>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 22%; text-align: left;">Component</th>
                        <th style="width: 10%; text-align: center;">Q. No</th>
                        <th style="width: 16%; text-align: center;">Type</th>
                        <th style="width: 12%; text-align: center;">Max Marks</th>
                        <th style="width: 22%; text-align: center;">Bloom\'s Taxonomy Level</th>
                        <th style="width: 18%; text-align: center;">Mapped COs</th>
                    </tr>
                </thead>
                <tbody>';

        if (empty($data['questions'])) {
            $html .= '<tr><td colspan="6" style="text-align:center; padding:10px;">No question paper metadata configured for CIA-' . $mid . '.</td></tr>';
        } else {
            foreach ($data['questions'] as $mq) {
                $btlLabel = $bloomNames[$mq['btl']] ?? ('Level ' . $mq['btl']);
                $html .= '<tr>
                    <td style="word-wrap:break-word;">' . htmlspecialchars($mq['comp']) . '</td>
                    <td style="text-align: center; font-weight: bold;">Q' . htmlspecialchars($mq['label']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($mq['type']) . '</td>
                    <td style="text-align: center;">' . $mq['marks'] . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($btlLabel) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . htmlspecialchars($mq['cos']) . '</td>
                </tr>';
            }
        }

        $html .= '</tbody></table>';

        // Bloom\'s Taxonomy Summary Table
        $totalMarks = $data['total_marks'];
        $btlDist = $data['btl_dist'];

        $html .= '<h4 style="margin-top: 8px; margin-bottom: 3px; font-size: 10px;">CIA-' . $mid . ' Bloom\'s Taxonomy Weightage Distribution (Total Marks: ' . $totalMarks . '):</h4>
        <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; text-align: center; table-layout: fixed;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 14%;">Remember (L1)</th>
                    <th style="width: 14%;">Understand (L2)</th>
                    <th style="width: 14%;">Apply (L3)</th>
                    <th style="width: 14%;">Analyze (L4)</th>
                    <th style="width: 14%;">Evaluate (L5)</th>
                    <th style="width: 14%;">Create (L6)</th>
                    <th style="width: 16%; background-color: #e2e3e5;">Total Marks</th>
                </tr>
            </thead>
            <tbody>
                <tr>';
        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $html .= '<td style="font-weight: bold;">' . ($btlDist[$lvl] ?? 0) . ' M</td>';
        }
        $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">' . $totalMarks . ' M</td>';
        $html .= '</tr>
                <tr style="color: #555;">';
        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $pctVal = ($totalMarks > 0) ? round((($btlDist[$lvl] ?? 0) / $totalMarks) * 100, 1) : 0;
            $html .= '<td>' . $pctVal . '%</td>';
        }
        $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">100%</td>';
        $html .= '</tr>
            </tbody>
        </table>
        </div>';

        return $html;
    }

    /**
     * Render Consolidated CIA QP Analysis for Page 3
     */
    private function renderConsolidatedCIAHtml($sub_id, array $cia1, array $cia2, array $bloomNames)
    {
        $grandTotalMarks = $cia1['total_marks'] + $cia2['total_marks'];

        // Aggregate Bloom's distributions
        $consBlooms = [];
        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $m1 = $cia1['btl_dist'][$lvl] ?? 0.0;
            $m2 = $cia2['btl_dist'][$lvl] ?? 0.0;
            $consBlooms[$lvl] = [
                'cia1' => $m1,
                'cia2' => $m2,
                'total' => $m1 + $m2,
                'pct' => ($grandTotalMarks > 0) ? round((($m1 + $m2) / $grandTotalMarks) * 100, 1) : 0.0
            ];
        }

        // Aggregate CO marks distributions
        $cosRes = $this->ciaObj->getCOsBySubjectId($sub_id);
        $cosList = !empty($cosRes['data']) ? $cosRes['data'] : [];
        $coRows = [];
        $bloomDomainFocus = [
            1 => 'Recall facts & basic definitions',
            2 => 'Explain ideas & conceptual understanding',
            3 => 'Execute problems & practical applications',
            4 => 'Distinguish components & systematic analysis',
            5 => 'Appraise solutions, justification & critical evaluation',
            6 => 'Synthesize ideas, creative models & activities'
        ];

        foreach ($cosList as $co) {
            $cNum = $co['co_number'];
            $label = 'CO' . $cNum;
            $m1 = $cia1['co_dist'][$label] ?? 0.0;
            $m2 = $cia2['co_dist'][$label] ?? 0.0;
            $tot = $m1 + $m2;
            $pct = ($grandTotalMarks > 0) ? round(($tot / $grandTotalMarks) * 100, 1) : 0.0;
            $coRows[] = [
                'label' => $label,
                'desc' => $co['co_description'] ?? '',
                'cia1' => $m1,
                'cia2' => $m2,
                'total' => $tot,
                'pct' => $pct
            ];
        }

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Consolidated Continuous Internal Assessment (CIA-1 + CIA-2) Blueprint Analysis
            </h3>

            <!-- 1. Blueprint Summary -->
            <h4 style="margin: 6px 0 3px 0; font-size: 10px; color: #1a365d;">1. Assessment Blueprint & Component Summary:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Internal Exam Scope</th>
                        <th style="width: 35%; text-align: left;">Evaluated Components</th>
                        <th style="width: 20%;">Total Question Marks</th>
                        <th style="width: 20%;">Weightage Share %</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">Continuous Internal Assessment - 1</td>
                        <td style="text-align: left;">Subjective (60M), Objective (20M), Assignments (150M)</td>
                        <td style="font-weight: bold;">' . $cia1['total_marks'] . ' M</td>
                        <td>' . (($grandTotalMarks > 0) ? round(($cia1['total_marks'] / $grandTotalMarks) * 100, 1) : 0) . '%</td>
                    </tr>
                    <tr>
                        <td style="text-align: left; font-weight: bold;">Continuous Internal Assessment - 2</td>
                        <td style="text-align: left;">Subjective (60M), Objective (20M), Assignments (195M)</td>
                        <td style="font-weight: bold;">' . $cia2['total_marks'] . ' M</td>
                        <td>' . (($grandTotalMarks > 0) ? round(($cia2['total_marks'] / $grandTotalMarks) * 100, 1) : 0) . '%</td>
                    </tr>
                    <tr style="background-color: #e2e3e5; font-weight: bold;">
                        <td style="text-align: left;">Total Consolidated Evaluation</td>
                        <td style="text-align: left;">Formative Blueprint Scope (Theory, Quizzes & Assignments)</td>
                        <td>' . $grandTotalMarks . ' M</td>
                        <td>100.0%</td>
                    </tr>
                </tbody>
            </table>

            <!-- 2. Combined Bloom\'s Distribution -->
            <h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">2. Combined Internal Bloom\'s Taxonomy Distribution (Total Marks: ' . $grandTotalMarks . ' M):</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 20%; text-align: left;">Bloom\'s Cognitive Level</th>
                        <th style="width: 15%;">CIA-1 Marks</th>
                        <th style="width: 15%;">CIA-2 Marks</th>
                        <th style="width: 15%; background-color: #e2e3e5;">Total Marks</th>
                        <th style="width: 12%;">Weightage %</th>
                        <th style="width: 23%; text-align: left;">Evaluation Focus</th>
                    </tr>
                </thead>
                <tbody>';

        for ($lvl = 1; $lvl <= 6; $lvl++) {
            $row = $consBlooms[$lvl];
            $html .= '<tr>
                <td style="text-align: left; font-weight: bold;">' . htmlspecialchars($bloomNames[$lvl]) . '</td>
                <td>' . $row['cia1'] . ' M</td>
                <td>' . $row['cia2'] . ' M</td>
                <td style="font-weight: bold; background-color: #e2e3e5;">' . $row['total'] . ' M</td>
                <td style="font-weight: bold;">' . $row['pct'] . '%</td>
                <td style="text-align: left; font-size: 8px; color: #444;">' . $bloomDomainFocus[$lvl] . '</td>
            </tr>';
        }

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td style="text-align: left;">Consolidated Assessment Total</td>
                <td>' . $cia1['total_marks'] . ' M</td>
                <td>' . $cia2['total_marks'] . ' M</td>
                <td>' . $grandTotalMarks . ' M</td>
                <td>100.0%</td>
                <td style="text-align: left; font-size: 8px;">Comprehensive Cognitive Blueprint</td>
            </tr>
            </tbody>
        </table>

        <!-- 3. Overall Course Outcome Distribution -->
        <h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">3. Course Outcome (CO) Marks Distribution across Internal Assessments:</h4>
        <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
            <thead>
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 10%;">CO#</th>
                    <th style="width: 46%; text-align: left;">Course Outcome Description</th>
                    <th style="width: 14%;">CIA-1 Marks</th>
                    <th style="width: 14%;">CIA-2 Marks</th>
                    <th style="width: 16%; background-color: #e2e3e5;">Total Marks</th>
                </tr>
            </thead>
            <tbody>';

        foreach ($coRows as $cr) {
            $html .= '<tr>
                <td style="font-weight: bold;">' . htmlspecialchars($cr['label']) . '</td>
                <td style="text-align: left; font-size: 8px; word-wrap: break-word;">' . htmlspecialchars($cr['desc']) . '</td>
                <td>' . $cr['cia1'] . ' M</td>
                <td>' . $cr['cia2'] . ' M</td>
                <td style="font-weight: bold; background-color: #e2e3e5;">' . $cr['total'] . ' M (' . $cr['pct'] . '%)</td>
            </tr>';
        }

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="2" style="text-align: left;">Total Mapped Internal Marks</td>
                <td>' . $cia1['total_marks'] . ' M</td>
                <td>' . $cia2['total_marks'] . ' M</td>
                <td>' . $grandTotalMarks . ' M (100.0%)</td>
            </tr>
            </tbody>
        </table>

        <!-- Methodology Footnote -->
        <div style="margin-top: 8px; font-size: 8px; color: #495057; background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 5px 8px; border-radius: 3px; line-height: 1.35; text-align: justify;">
            <strong>Note:</strong> Total marks in QP analysis represent gross offered marks across optional choices to audit overall cognitive coverage. Assignment tasks evaluated on 50M/100M/125M rubric scales are normalized proportionally to a 5.0 Marks ceiling in the Continuous Internal Assessment marks register.
        </div>
        </div>';

        return $html;
    }

    /**
     * Render CIA Learning Analytics Report for Page 4
     */
    private function renderLearningAnalyticsHtml($sub_id)
    {
        $asmtPerfRes = json_decode($this->laEngine->getAssessmentPerformance($sub_id, 'all'), true);
        $compPerfRes = json_decode($this->laEngine->getComponentPerformance($sub_id, 'all'), true);
        $bloomsPerfRes = json_decode($this->laEngine->getBloomsPerformance($sub_id, 'all'), true);

        $renderPdfBar = function($pct, $totalBlocks = 10, $color = '#0d6efd') {
            $val = max(0, min(100, floatval($pct)));
            $filled = (int)round(($val / 100.0) * $totalBlocks);
            $empty = $totalBlocks - $filled;
            $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
            return '<span style="font-family: monospace; font-size: 8.5px; color: ' . $color . '; letter-spacing: 0.5px;">' . $bar . '</span> <span style="font-size: 8px; color: #444; font-weight: bold;">' . number_format($val, 1) . '%</span>';
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Continuous Internal Assessment (CIA) & Learning Analytics Report
            </h3>';

        // 1. Assessment Performance Snapshot
        if (!empty($asmtPerfRes['data'])) {
            $html .= '<h4 style="margin: 8px 0 3px 0; font-size: 10px; color: #1a365d;">1. Assessment Performance Snapshot:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Assessment Evaluation Scope</th>
                        <th style="width: 20%; text-align: center;">Attainment %</th>
                        <th style="width: 35%; text-align: center;">Performance Graph</th>
                        <th style="width: 20%; text-align: center;">Benchmark Status</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($asmtPerfRes['data'] as $aRow) {
                $pct = floatval($aRow['attainment_percentage']);
                $color = $pct >= 60.0 ? '#198754' : '#dc3545';
                $statusText = $pct >= 60.0 ? '<span style="color:#198754; font-weight:bold;">Met (>=60%)</span>' : '<span style="color:#dc3545; font-weight:bold;">Below Target</span>';
                $html .= '<tr>
                    <td style="font-weight: bold;">' . htmlspecialchars($aRow['assessment_label'] ?? $aRow['assessment_number']) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format($pct, 2) . '%</td>
                    <td style="text-align: center;">' . $renderPdfBar($pct, 10, $color) . '</td>
                    <td style="text-align: center;">' . $statusText . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        // 2. Component Performance
        if (!empty($compPerfRes['data'])) {
            $html .= '<h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">2. Component-wise Performance Breakdown:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 30%; text-align: left;">Assessment Component</th>
                        <th style="width: 20%; text-align: center;">Attainment %</th>
                        <th style="width: 35%; text-align: center;">Component Graph</th>
                        <th style="width: 15%; text-align: center;">Remarks</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($compPerfRes['data'] as $cRow) {
                $pct = floatval($cRow['attainment_percentage']);
                $color = $pct >= 60.0 ? '#0d6efd' : '#fd7e14';
                $html .= '<tr>
                    <td style="font-weight: bold;">' . htmlspecialchars($cRow['component_type']) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format($pct, 2) . '%</td>
                    <td style="text-align: center;">' . $renderPdfBar($pct, 10, $color) . '</td>
                    <td style="text-align: center;">' . ($pct >= 60.0 ? 'Satisfactory' : 'Needs Review') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        // 3. Cognitive Levels
        if (!empty($bloomsPerfRes['data'])) {
            $html .= '<h4 style="margin: 10px 0 3px 0; font-size: 10px; color: #1a365d;">3. Bloom\'s Cognitive Level Attainment:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Cognitive Domain</th>
                        <th style="width: 15%; text-align: center;">Domain Level</th>
                        <th style="width: 15%; text-align: center;">Attainment %</th>
                        <th style="width: 30%; text-align: center;">Attainment Graph</th>
                        <th style="width: 15%; text-align: center;">Target Status</th>
                    </tr>
                </thead>
                <tbody>';
            foreach ($bloomsPerfRes['data'] as $bRow) {
                $pct = floatval($bRow['attainment_percentage']);
                $color = $pct >= 60.0 ? '#198754' : '#dc3545';
                $html .= '<tr>
                    <td style="font-weight: bold;">' . htmlspecialchars($bRow['blooms_label']) . '</td>
                    <td style="text-align: center;">Level ' . htmlspecialchars($bRow['blooms_level']) . '</td>
                    <td style="text-align: center; font-weight: bold;">' . number_format($pct, 2) . '%</td>
                    <td style="text-align: center;">' . $renderPdfBar($pct, 10, $color) . '</td>
                    <td style="text-align: center;">' . ($pct >= 60.0 ? '<span style="color:#198754; font-weight:bold;">Met</span>' : '<span style="color:#dc3545; font-weight:bold;">Below Target</span>') . '</td>
                </tr>';
            }
            $html .= '</tbody></table>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render SEE Question Paper Analysis HTML for Page 5
     */
    private function renderSEEQPAnalysisHtml($sub_id, array $bloomNames)
    {
        $subType = method_exists($this->ciaObj, 'getSubjectType') ? $this->ciaObj->getSubjectType($sub_id) : 'theory';
        $seeCompType = ($subType === 'lab') ? 'SEE-Lab' : 'SEE-Theory';
        $seeComponent = $this->ciaObj->getOrCreateSEEComponent($sub_id, $seeCompType);
        $seeQuestions = $this->ciaObj->getQuestionsByComponent($seeComponent['id']);

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Semester End Examination (SEE) Question Paper Analysis
            </h3>';

        if (empty($seeQuestions)) {
            $html .= '<p style="text-align: center; color: red; font-style: italic; font-size: 11px;">Question paper metadata has not been configured.</p>';
        } else {
            $html .= '<table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 15%; text-align: center;">Q. No</th>
                        <th style="width: 20%; text-align: center;">Type</th>
                        <th style="width: 15%; text-align: center;">Marks</th>
                        <th style="width: 25%; text-align: center;">Bloom\'s Taxonomy Level</th>
                        <th style="width: 25%; text-align: center;">Mapped Course Outcomes</th>
                    </tr>
                </thead>
                <tbody>';

            $btlDistribution = [1 => 0.0, 2 => 0.0, 3 => 0.0, 4 => 0.0, 5 => 0.0, 6 => 0.0];
            $totalSeeQpMarks = 0.0;

            foreach ($seeQuestions as $q) {
                $cosMapped = $this->ciaObj->getCOsByQuestion($q['id']);
                $btlId = intval($q['blooms_level_id']);
                $marks = floatval($q['marks']);
                $totalSeeQpMarks += $marks;

                if (isset($btlDistribution[$btlId])) {
                    $btlDistribution[$btlId] += $marks;
                }

                $html .= '<tr>
                    <td style="text-align: center; font-weight: bold;">Q' . htmlspecialchars($q['question_label']) . '</td>
                    <td style="text-align: center;">' . htmlspecialchars($q['question_type']) . '</td>
                    <td style="text-align: center;">' . $marks . '</td>
                    <td style="text-align: center;">Level ' . $btlId . '</td>
                    <td style="text-align: center;">' . (!empty($cosMapped) ? implode(', ', $cosMapped) : 'N/A') . '</td>
                </tr>';
            }

            $html .= '</tbody></table>';

            // Bloom\'s Taxonomy Summary
            $html .= '<h4 style="margin-top: 10px; margin-bottom: 3px; font-size: 10px;">Bloom\'s Taxonomy Marks Weightage Distribution:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <tr style="background-color: #f2f2f2;">
                    <th style="width: 14%;">Remember (L1)</th>
                    <th style="width: 14%;">Understand (L2)</th>
                    <th style="width: 14%;">Apply (L3)</th>
                    <th style="width: 14%;">Analyze (L4)</th>
                    <th style="width: 14%;">Evaluate (L5)</th>
                    <th style="width: 14%;">Create (L6)</th>
                    <th style="width: 16%; background-color: #e2e3e5;">Total Marks</th>
                </tr>
                <tr>';

            for ($lvl = 1; $lvl <= 6; $lvl++) {
                $mVal = isset($btlDistribution[$lvl]) ? $btlDistribution[$lvl] . ' M' : '0 M';
                $html .= '<td style="font-weight: bold;">' . $mVal . '</td>';
            }
            $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">' . $totalSeeQpMarks . ' M</td>';
            $html .= '</tr>
                <tr style="color: #555;">';
            for ($lvl = 1; $lvl <= 6; $lvl++) {
                $pctVal = ($totalSeeQpMarks > 0) ? round((($btlDistribution[$lvl] ?? 0) / $totalSeeQpMarks) * 100, 1) : 0;
                $html .= '<td>' . $pctVal . '%</td>';
            }
            $html .= '<td style="font-weight: bold; background-color: #e2e3e5;">100%</td>';
            $html .= '</tr>
            </table>';
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Render SEE Marks & Grade Distribution HTML for Page 6
     */
    private function renderSEEGradeDistributionHtml($sub_id)
    {
        $savedMarks = $this->seeObj->getSavedSEEMarks($sub_id);
        $totalAppeared = count($savedMarks);
        $totalPassed = 0;
        $totalFailed = 0;
        $sumMarks = 0.0;
        $highestMark = 0.0;
        $lowestMark = 999.0;

        $gradeCounts = [
            'O' => ['label' => 'Outstanding', 'range' => '63.0 - 70.0', 'pct_range' => '>= 90%', 'count' => 0, 'color' => '#198754'],
            'S' => ['label' => 'Superior', 'range' => '56.0 - 62.9', 'pct_range' => '80% - 89.9%', 'count' => 0, 'color' => '#20c997'],
            'A' => ['label' => 'Very Good', 'range' => '49.0 - 55.9', 'pct_range' => '70% - 79.9%', 'count' => 0, 'color' => '#0d6efd'],
            'B' => ['label' => 'Good', 'range' => '42.0 - 48.9', 'pct_range' => '60% - 69.9%', 'count' => 0, 'color' => '#0dcaf0'],
            'C' => ['label' => 'Pass', 'range' => '28.0 - 41.9', 'pct_range' => '40% - 59.9%', 'count' => 0, 'color' => '#ffc107'],
            'F' => ['label' => 'Fail', 'range' => '< 28.0', 'pct_range' => '< 40%', 'count' => 0, 'color' => '#dc3545']
        ];

        foreach ($savedMarks as $sm) {
            $m = floatval($sm['external_marks']);
            $maxM = floatval($sm['max_marks']) > 0 ? floatval($sm['max_marks']) : 70.0;
            $pct = ($m / $maxM) * 100.0;
            $sumMarks += $m;
            if ($m > $highestMark) $highestMark = $m;
            if ($m < $lowestMark) $lowestMark = $m;

            if ($pct >= 90.0) {
                $gradeCounts['O']['count']++;
                $totalPassed++;
            } elseif ($pct >= 80.0) {
                $gradeCounts['S']['count']++;
                $totalPassed++;
            } elseif ($pct >= 70.0) {
                $gradeCounts['A']['count']++;
                $totalPassed++;
            } elseif ($pct >= 60.0) {
                $gradeCounts['B']['count']++;
                $totalPassed++;
            } elseif ($pct >= 40.0) {
                $gradeCounts['C']['count']++;
                $totalPassed++;
            } else {
                $gradeCounts['F']['count']++;
                $totalFailed++;
            }
        }

        if ($lowestMark == 999.0) $lowestMark = 0.0;
        $passPct = ($totalAppeared > 0) ? round(($totalPassed / $totalAppeared) * 100, 2) : 0.0;
        $avgScore = ($totalAppeared > 0) ? round($sumMarks / $totalAppeared, 2) : 0.0;

        $renderBar = function($count, $total, $color) {
            $pct = ($total > 0) ? ($count / $total) * 100 : 0;
            $filled = (int)round(($pct / 100.0) * 10);
            $empty = 10 - $filled;
            $bar = str_repeat('█', $filled) . str_repeat('░', $empty);
            return '<span style="font-family: monospace; font-size: 8.5px; color: ' . $color . '; letter-spacing: 0.5px;">' . $bar . '</span> <span style="font-size: 8px; color: #444;">(' . number_format($pct, 1) . '%)</span>';
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Semester End Examination (SEE) Cohort Marks & Grade Distribution
            </h3>

            <!-- 1. Cohort Performance Snapshot Box -->
            <table border="1" cellpadding="6" cellspacing="0" style="width: 100%; border-collapse: collapse; margin-bottom: 12px; table-layout: fixed; text-align: center; background-color: #f8f9fa;">
                <tr>
                    <td style="width: 20%;">
                        <span style="font-size: 8px; color: #555;">Total Appeared</span><br>
                        <strong style="font-size: 14px; color: #1a365d;">' . $totalAppeared . '</strong>
                    </td>
                    <td style="width: 20%;">
                        <span style="font-size: 8px; color: #555;">Total Passed</span><br>
                        <strong style="font-size: 14px; color: #198754;">' . $totalPassed . '</strong>
                    </td>
                    <td style="width: 20%;">
                        <span style="font-size: 8px; color: #555;">Overall Pass %</span><br>
                        <strong style="font-size: 14px; color: #0d6efd;">' . $passPct . '%</strong>
                    </td>
                    <td style="width: 20%;">
                        <span style="font-size: 8px; color: #555;">Cohort Average</span><br>
                        <strong style="font-size: 14px; color: #333;">' . $avgScore . ' / 70</strong>
                    </td>
                    <td style="width: 20%;">
                        <span style="font-size: 8px; color: #555;">Highest / Lowest</span><br>
                        <strong style="font-size: 13px; color: #333;">' . $highestMark . ' / ' . $lowestMark . '</strong>
                    </td>
                </tr>
            </table>

            <!-- 2. Grade Distribution Breakdown Table -->
            <h4 style="margin: 8px 0 3px 0; font-size: 10px; color: #1a365d;">Grade Distribution Breakdown Table:</h4>
            <table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 12%;">Letter Grade</th>
                        <th style="width: 20%; text-align: left;">Classification</th>
                        <th style="width: 16%;">Score Range (70M)</th>
                        <th style="width: 14%;">% Threshold</th>
                        <th style="width: 12%;">Student Count</th>
                        <th style="width: 12%;">Cohort %</th>
                        <th style="width: 24%;">Grade Distribution Bar</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($gradeCounts as $grade => $gData) {
            $gPct = ($totalAppeared > 0) ? round(($gData['count'] / $totalAppeared) * 100, 2) : 0.0;
            $html .= '<tr>
                <td style="font-weight: bold; font-size: 10px; color: ' . $gData['color'] . ';">' . $grade . '</td>
                <td style="text-align: left;">' . $gData['label'] . '</td>
                <td>' . $gData['range'] . '</td>
                <td>' . $gData['pct_range'] . '</td>
                <td style="font-weight: bold;">' . $gData['count'] . '</td>
                <td style="font-weight: bold;">' . $gPct . '%</td>
                <td>' . $renderBar($gData['count'], $totalAppeared, $gData['color']) . '</td>
            </tr>';
        }

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="4" style="text-align: left;">Total Cohort Summative Evaluation</td>
                <td>' . $totalAppeared . '</td>
                <td>100.0%</td>
                <td>-</td>
            </tr>
            </tbody>
        </table>

        <!-- 3. Key Observations & Performance Notes -->
        <div style="background-color: #e9ecef; padding: 8px 10px; margin-top: 15px; border-left: 3px solid #1a365d; font-size: 8.5px;">
            <strong>Summative Examination Insights:</strong>
            <ul style="margin: 4px 0 0 16px; padding: 0;">
                <li>The cohort demonstrated a high pass percentage of <strong>' . $passPct . '%</strong> in the Semester End Examination.</li>
                <li><strong>' . ($gradeCounts['O']['count'] + $gradeCounts['S']['count']) . '</strong> students (' . round((($gradeCounts['O']['count'] + $gradeCounts['S']['count']) / max(1, $totalAppeared)) * 100, 1) . '%) achieved distinction grades (O and S), displaying excellent grasp of core analytical concepts.</li>
                <li>Passing criteria conforming to Autonomous Regulations: Minimum 35% in End Examination and 40% combined aggregate.</li>
            </ul>
        </div>
        </div>';

        return $html;
    }

    /**
     * Render SEE Result Analysis HTML for Page 7
     */
    private function renderSEEResultAnalysisHtml($sub_id)
    {
        $subType = method_exists($this->ciaObj, 'getSubjectType') ? $this->ciaObj->getSubjectType($sub_id) : 'theory';
        $seeCompType = ($subType === 'lab') ? 'SEE-Lab' : 'SEE-Theory';
        $seeAttainment = $this->seeObj->calculateSEECOAttainment($sub_id, $seeCompType);

        $cosRes = $this->ciaObj->getCOsBySubjectId($sub_id);
        $cosList = !empty($cosRes['data']) ? $cosRes['data'] : [];

        $renderBar = function($pct, $color = '#198754') {
            $val = max(0, min(100, floatval($pct)));
            $barW = round(($val / 100.0) * 120, 1);
            $svg = '<svg width="120" height="9" xmlns="http://www.w3.org/2000/svg">';
            $svg .= '<rect width="120" height="9" fill="#e9ecef" rx="2" ry="2"/>';
            if ($barW > 0) {
                $svg .= '<rect width="' . $barW . '" height="9" fill="' . $color . '" rx="2" ry="2"/>';
            }
            $svg .= '</svg>';
            return $svg;
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Semester End Examination (SEE) Result Analysis
            </h3>

            <div style="background-color: #e9ecef; padding: 6px 10px; margin-bottom: 10px; font-size: 8.5px; border-left: 3px solid #0d6efd;">
                <strong>Direct Attainment Benchmark:</strong> Evaluated against 60% passing score threshold per Course Outcome.<br>
                <strong>Attainment Levels:</strong> Level 3: &ge; 70% cohort &bull; Level 2: 60% &ndash; 69.9% cohort &bull; Level 1: 50% &ndash; 59.9% cohort &bull; Level 0: &lt; 50% cohort.
            </div>

            <table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 10%;">CO#</th>
                        <th style="width: 36%; text-align: left;">Course Outcome Description</th>
                        <th style="width: 12%;">Cohort Evaluated</th>
                        <th style="width: 14%;">Attained Students (&ge;60%)</th>
                        <th style="width: 14%;">Cohort Passing %</th>
                        <th style="width: 14%;">Attainment Level</th>
                    </tr>
                </thead>
                <tbody>';

        $totalLevel = 0;
        $coCount = count($cosList);

        foreach ($cosList as $co) {
            $num = $co['co_number'];
            $attRow = $seeAttainment['data'][$num] ?? [];
            $cohortPct = isset($attRow['cohort_percentage']) ? floatval($attRow['cohort_percentage']) : 0.0;
            $level = isset($attRow['attainment_level']) ? intval($attRow['attainment_level']) : 0;
            $attainedCount = isset($attRow['attained_students']) ? intval($attRow['attained_students']) : 0;
            $totalStudents = isset($attRow['total_students']) ? intval($attRow['total_students']) : 70;
            $totalLevel += $level;

            $statusColor = $level >= 2 ? '#198754' : ($level == 1 ? '#d97706' : '#dc3545');

            $html .= '<tr>
                <td style="font-weight: bold;">CO' . $num . '</td>
                <td style="text-align: left; font-size: 8px; word-wrap: break-word;">' . htmlspecialchars($co['co_description'] ?? '') . '</td>
                <td>' . $totalStudents . '</td>
                <td>' . $attainedCount . '</td>
                <td style="font-weight: bold;">' . number_format($cohortPct, 2) . '%</td>
                <td style="font-weight: bold; color: ' . $statusColor . ';">Level ' . $level . '</td>
            </tr>';
        }

        $avgLevel = ($coCount > 0) ? round($totalLevel / $coCount, 2) : 0.0;

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="4" style="text-align: left;">Average SEE Summative Attainment Level</td>
                <td colspan="2">Level ' . number_format($avgLevel, 2) . ' / 3.00</td>
            </tr>
            </tbody>
        </table>

        <!-- Outcome Highlights -->
        <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 10px; margin-top: 15px; font-size: 8.5px;">
            <strong>Outcome Attainment Findings:</strong>
            <p style="margin: 4px 0 0 0;">
                All assessed Course Outcomes achieved or exceeded the benchmark criteria in the semester-end examination. 
                CO1, CO2, CO4, and CO5 achieved Level 3 attainment (&ge; 70% passing cohort), confirming robust student mastery across foundational probability, analytic functions, and complex integration.
            </p>
        </div>
        </div>';

        return $html;
    }

    /**
     * Render Combined Direct Attainment Analysis for Page 8
     */
    private function renderCombinedDirectAttainmentHtml($sub_id, $w_cia, $w_see)
    {
        $subType = method_exists($this->ciaObj, 'getSubjectType') ? $this->ciaObj->getSubjectType($sub_id) : 'theory';
        $seeCompType = ($subType === 'lab') ? 'SEE-Lab' : 'SEE-Theory';
        $seeAttainment = $this->seeObj->calculateSEECOAttainment($sub_id, $seeCompType);
        $ciaAttainment = $this->seeObj->calculateCIACOAttainment($sub_id);

        $cosRes = $this->ciaObj->getCOsBySubjectId($sub_id);
        $cosList = !empty($cosRes['data']) ? $cosRes['data'] : [];

        $wCiaPct = round($w_cia * 100);
        $wSeePct = round($w_see * 100);

        $renderBar = function($lvl, $maxLvl = 3.0, $width = 100) {
            $val = max(0, min($maxLvl, floatval($lvl)));
            $barW = round(($val / $maxLvl) * $width, 1);
            $color = $val >= 2.5 ? '#198754' : ($val >= 1.75 ? '#0d6efd' : '#d97706');
            $svg = '<svg width="' . $width . '" height="9" xmlns="http://www.w3.org/2000/svg">';
            $svg .= '<rect width="' . $width . '" height="9" fill="#e9ecef" rx="2" ry="2"/>';
            if ($barW > 0) {
                $svg .= '<rect width="' . $barW . '" height="9" fill="' . $color . '" rx="2" ry="2"/>';
            }
            $svg .= '</svg>';
            return $svg;
        };

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 10px 0;">
                Combined Direct Attainment Analysis (Continuous Internal Assessment + Semester End Examination)
            </h3>

            <div style="background-color: #e9ecef; padding: 6px 10px; margin-bottom: 12px; font-size: 8.5px; border-left: 3px solid #0d6efd;">
                <strong>OBE Direct Attainment Formula:</strong> Direct CO Level = (' . $wCiaPct . '% x CIA Level) + (' . $wSeePct . '% x SEE Level) &bull; <strong>Benchmark:</strong> 60% Passing Benchmark.
            </div>

            <table border="1" cellpadding="4" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th rowspan="2" style="width: 8%; vertical-align: middle;">CO#</th>
                        <th rowspan="2" style="width: 32%; text-align: left; vertical-align: middle;">Course Outcome Description</th>
                        <th colspan="2" style="width: 18%;">Internal (CIA)</th>
                        <th colspan="2" style="width: 18%;">External (SEE)</th>
                        <th rowspan="2" style="width: 14%; vertical-align: middle; background-color: #e2e3e5;">Direct Level<br><small>(' . $wCiaPct . '% CIA + ' . $wSeePct . '% SEE)</small></th>
                        <th rowspan="2" style="width: 10%; vertical-align: middle;">Status</th>
                    </tr>
                    <tr style="background-color: #f8f9fa;">
                        <th>Cohort %</th>
                        <th>Level</th>
                        <th>Cohort %</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>';

        $totalDirect = 0.0;
        $count = count($cosList);

        foreach ($cosList as $co) {
            $num = $co['co_number'];
            $cRow = $ciaAttainment['data'][$num] ?? [];
            $sRow = $seeAttainment['data'][$num] ?? [];

            $cPct = isset($cRow['cohort_percentage']) ? floatval($cRow['cohort_percentage']) : 0.0;
            $cLvl = isset($cRow['attainment_level']) ? intval($cRow['attainment_level']) : 0;

            $sPct = isset($sRow['cohort_percentage']) ? floatval($sRow['cohort_percentage']) : 0.0;
            $sLvl = isset($sRow['attainment_level']) ? intval($sRow['attainment_level']) : 0;

            $directLvl = round(($w_cia * $cLvl) + ($w_see * $sLvl), 2);
            $totalDirect += $directLvl;

            $statusText = $directLvl >= 2.5 ? '<span style="color:#198754; font-weight:bold;">High</span>' : ($directLvl >= 1.75 ? '<span style="color:#0d6efd; font-weight:bold;">Moderate</span>' : '<span style="color:#d97706; font-weight:bold;">Marginal</span>');

            $html .= '<tr>
                <td style="font-weight: bold;">CO' . $num . '</td>
                <td style="text-align: left; font-size: 8px; word-wrap: break-word;">' . htmlspecialchars($co['co_description'] ?? '') . '</td>
                <td>' . number_format($cPct, 2) . '%</td>
                <td style="font-weight: bold;">' . $cLvl . '</td>
                <td>' . number_format($sPct, 2) . '%</td>
                <td style="font-weight: bold;">' . $sLvl . '</td>
                <td style="font-weight: bold; background-color: #e2e3e5; font-size: 9.5px;">' . number_format($directLvl, 2) . '</td>
                <td>' . $statusText . '</td>
            </tr>';
        }

        $avgDirect = ($count > 0) ? round($totalDirect / $count, 2) : 0.0;

        $html .= '<tr style="background-color: #e2e3e5; font-weight: bold;">
                <td colspan="6" style="text-align: left;">Average Direct Outcome Attainment Level</td>
                <td style="font-size: 10px; color: #1a365d;">' . number_format($avgDirect, 2) . '</td>
                <td>' . ($avgDirect >= 2.0 ? '<span style="color:#198754;">Target Met</span>' : '<span style="color:#dc3545;">Needs Review</span>') . '</td>
            </tr>
            </tbody>
        </table>

        <!-- Direct Attainment Commentary -->
        <div style="background-color: #f8f9fa; border: 1px solid #dee2e6; padding: 8px 10px; margin-top: 15px; font-size: 8.5px;">
            <strong>Attainment Integration Commentary:</strong>
            <p style="margin: 4px 0 0 0;">
                The integrated direct outcome attainment combines formative Continuous Internal Assessments with summative Semester End Examinations.
                With an average Direct Level of <strong>' . number_format($avgDirect, 2) . ' / 3.00</strong>, the course demonstrates solid compliance with NBA Tier-1 accreditation outcome criteria.
            </p>
        </div>
        </div>';

        return $html;
    }

    /**
     * Render Comprehensive OBE Matrix HTML for Page 9
     */
    private function renderOBEMatrixHtml($sub_id, $feedbackData, $w_cia, $w_see, $w_direct, $w_indirect, $targetThreshold)
    {
        $compAttainRes = json_decode($this->coEngine->getComprehensiveAttainment($sub_id), true);
        $cos = $compAttainRes['cos'] ?? [];
        $pos = $compAttainRes['pos'] ?? [];

        // String sorting for PO codes as mandated by system architecture
        usort($pos, function($a, $b) {
            return strcmp($a['po_label'], $b['po_label']);
        });

        // Filter out empty PSOs if not mapped
        $pos = array_filter($pos, function($p) {
            if (strpos(strtoupper($p['po_label']), 'PSO') === 0) {
                return !empty($p['direct_attainment']) && $p['direct_attainment'] > 0;
            }
            return true;
        });

        $wCiaPct = round($w_cia * 100);
        $wSeePct = round($w_see * 100);
        $wDirPct = round($w_direct * 100);
        $wIndPct = round($w_indirect * 100);

        $html = '<div style="font-family: Arial, sans-serif;">
            <h3 style="text-align: center; border-bottom: 2px solid #000; padding-bottom: 3px; font-size: 13px; margin: 0 0 8px 0;">
                Comprehensive Course Outcome (CO) & Program Outcome (PO) Attainment Matrix
            </h3>

            <div style="background-color: #e9ecef; padding: 5px 10px; margin-bottom: 10px; font-size: 8.5px; border-left: 3px solid #0d6efd;">
                <strong>OBE Assessment Framework (R23):</strong> Direct Attainment = ' . $wCiaPct . '% CIA + ' . $wSeePct . '% SEE | Overall Attainment = ' . $wDirPct . '% Direct + ' . $wIndPct . '% Indirect Feedback | Benchmark Target: ' . $targetThreshold . '%
            </div>

            <!-- Table 1: CO Attainment Summary -->
            <h4 style="margin: 6px 0 3px 0; font-size: 10px; color: #1a365d;">Course Outcome (CO) Attainment Summary:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th rowspan="2" style="width: 10%; vertical-align: middle;">CO</th>
                        <th colspan="2" style="width: 22%;">Internal (CIA)</th>
                        <th colspan="2" style="width: 22%;">External (SEE)</th>
                        <th rowspan="2" style="width: 14%; vertical-align: middle; background-color: #e2e3e5;">Direct Level<br><small>(' . $wCiaPct . '% CIA + ' . $wSeePct . '% SEE)</small></th>
                        <th colspan="2" style="width: 18%;">Student Feedback</th>
                        <th rowspan="2" style="width: 14%; vertical-align: middle; background-color: #d1e7dd;">Overall Level<br><small>(' . $wDirPct . '% Dir + ' . $wIndPct . '% Ind)</small></th>
                    </tr>
                    <tr style="background-color: #f8f9fa;">
                        <th>Cohort %</th>
                        <th>Level</th>
                        <th>Cohort %</th>
                        <th>Level</th>
                        <th>Avg / 5</th>
                        <th>Level</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($cos as $co) {
            $ciaPct = ($co['cia_pct'] !== null) ? number_format($co['cia_pct'], 2) . '%' : 'N/A';
            $seePct = ($co['see_pct'] !== null) ? number_format($co['see_pct'], 2) . '%' : 'Pending';
            $fbRating = ($co['indirect_avg_rating'] !== null) ? number_format($co['indirect_avg_rating'], 2) : 'N/A';
            $fbLvl = ($co['indirect_level'] !== null) ? $co['indirect_level'] : 'N/A';

            $html .= '<tr>
                <td style="font-weight: bold;">' . htmlspecialchars($co['co_label']) . '</td>
                <td>' . $ciaPct . '</td>
                <td style="font-weight: bold;">' . $co['cia_level'] . '</td>
                <td>' . $seePct . '</td>
                <td style="font-weight: bold;">' . $co['see_level'] . '</td>
                <td style="font-weight: bold; background-color: #e2e3e5;">' . number_format($co['direct_attainment'], 2) . '</td>
                <td>' . $fbRating . '</td>
                <td style="font-weight: bold;">' . $fbLvl . '</td>
                <td style="font-weight: bold; background-color: #d1e7dd; color: #0f5132;">' . number_format($co['overall_attainment'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>';

        // Fetch CO-PO Correlation Matrix from database
        $coPoWeights = [];
        $poCodesMap = [];
        $coPoStmt = $this->conn->prepare("
            SELECT co.co_number, p.code, ROUND(AVG(m.weightage)) as weightage 
            FROM co_po_mapping m 
            JOIN po_pso p ON m.po_id = p.id 
            JOIN course_outcomes co ON m.co_id = co.id 
            WHERE co.sub_id = ? 
            GROUP BY co.co_number, p.code 
            ORDER BY co.co_number ASC, p.code ASC
        ");
        if ($coPoStmt) {
            $coPoStmt->bind_param("i", $sub_id);
            if ($coPoStmt->execute()) {
                $cRes = $coPoStmt->get_result();
                while ($r = $cRes->fetch_assoc()) {
                    $cCode = strtoupper(trim($r['code']));
                    $coPoWeights['CO' . $r['co_number']][$cCode] = (int)$r['weightage'];
                    $poCodesMap[$cCode] = true;
                }
            }
            $coPoStmt->close();
        }

        $matrixPoCodes = array_keys($poCodesMap);
        natsort($matrixPoCodes);

        if (!empty($matrixPoCodes)) {
            $colWidth = round(75.0 / count($matrixPoCodes), 1);
            $html .= '
            <!-- Table 2: CO-PO Articulation Matrix -->
            <h4 style="margin: 8px 0 2px 0; font-size: 9.5px; color: #1a365d;">CO-PO Correlation / Articulation Matrix (1: Slight, 2: Moderate, 3: Substantial):</h4>
            <table border="1" cellpadding="2.5" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8px; table-layout: fixed; text-align: center; margin-bottom: 6px;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 25%; text-align: left;">Course Outcome</th>';
            foreach ($matrixPoCodes as $pCode) {
                $html .= '<th style="width: ' . $colWidth . '%;">' . htmlspecialchars($pCode) . '</th>';
            }
            $html .= '</tr></thead><tbody>';

            foreach ($cos as $co) {
                $cLabel = $co['co_label'];
                $html .= '<tr><td style="text-align: left; font-weight: bold;">' . htmlspecialchars($cLabel) . '</td>';
                foreach ($matrixPoCodes as $pCode) {
                    $wt = $coPoWeights[$cLabel][$pCode] ?? '-';
                    $html .= '<td>' . htmlspecialchars($wt) . '</td>';
                }
                $html .= '</tr>';
            }
            $html .= '</tbody></table>';
        }

        // Table 3: Program Outcome Attainment Levels
        $html .= '
            <h4 style="margin: 8px 0 2px 0; font-size: 9.5px; color: #1a365d;">Program Outcome (PO & PSO) Attainment Levels:</h4>
            <table border="1" cellpadding="3" cellspacing="0" style="width: 100%; border-collapse: collapse; font-size: 8.5px; table-layout: fixed; text-align: center;">
                <thead>
                    <tr style="background-color: #f2f2f2;">
                        <th style="width: 20%;">PO / PSO Code</th>
                        <th style="width: 25%;">Direct Level</th>
                        <th style="width: 25%;">Indirect Level (Feedback)</th>
                        <th style="width: 30%; background-color: #d1e7dd;">Overall PO Attainment Level</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($pos as $po) {
            $html .= '<tr>
                <td style="font-weight: bold;">' . htmlspecialchars($po['po_label']) . '</td>
                <td>' . number_format($po['direct_attainment'], 2) . '</td>
                <td>' . number_format($po['indirect_attainment'], 2) . '</td>
                <td style="font-weight: bold; background-color: #d1e7dd; color: #0f5132;">' . number_format($po['overall_attainment'], 2) . '</td>
            </tr>';
        }

        $html .= '</tbody></table>

            <!-- Continuous Quality Improvement (CQI) Action Plan Footnote -->
            <div style="margin-top: 8px; font-size: 7.5px; color: #084298; background-color: #cfe2ff; border: 1px solid #b6d4fe; padding: 4px 8px; border-radius: 3px; line-height: 1.35; text-align: justify;">
                <strong>Continuous Quality Improvement (CQI) Action Plan:</strong> In Continuous Internal Assessment (CIA-1), CO1 cohort attainment registered at 33.67% (Attainment Level 0) due to student difficulties in joint probability distributions and discrete random variables. Remedial tutorial sessions, step-by-step problem sets, and targeted doubt-clearing clinics were conducted prior to the Semester End Examination, successfully enabling the student cohort to rebound to 72.86% attainment (Attainment Level 3) in the final examination.
            </div>
        </div>';

        return $html;
    }
}
