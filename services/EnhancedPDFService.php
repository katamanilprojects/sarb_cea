<?php
/**
 * Enhanced PDF Service for Feedback Reports
 * 
 * Provides professional multi-page PDF generation with improved UI/UX
 * using mPDF with enhanced formatting, layout, and organization
 */

require_once __DIR__ . '/../dbcredentials.class.php';

class EnhancedPDFService
{
    private $mpdf;
    private $conn;
    private $feedbackService;
    
    // Professional color scheme
    private $colors = [
        'primary' => '#1a365d',      // Navy blue for headers
        'secondary' => '#2c5282',    // Lighter blue for subheaders
        'accent' => '#2b6cb0',       // Accent blue
        'success' => '#276749',     // Green for positive indicators
        'warning' => '#d97706',     // Orange for warnings
        'danger' => '#c53030',      // Red for alerts
        'light' => '#f7fafc',        // Light background
        'border' => '#e2e8f0',       // Border color
        'text' => '#1a202c',        // Text color
        'muted' => '#718096',       // Muted text
    ];
    
    public function __construct()
    {
        $db = DBCredentials::getInstance();
        $this->conn = $db->getConnection();
        
        require_once __DIR__ . '/../vendor/autoload.php';
        
        // Initialize mPDF with professional settings
        $this->mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 20,
            'margin_bottom' => 15,
            'margin_header' => 10,
            'margin_footer' => 10,
            'default_font_size' => 10,
            'default_font' => 'helvetica',
            'tempDir' => __DIR__ . '/../uploads/tmp'
        ]);
        
        require_once __DIR__ . '/../feedbackservice.class.php';
        $this->feedbackService = new FeedbackService();
    }
    
    /**
     * Generate comprehensive multi-page PDF report
     */
    public function generateFeedbackReport($level, $params)
    {
        try {
            // Fetch feedback data
            $data = $this->fetchFeedbackData($level, $params);
            
            if (empty($data) || ($data['status'] ?? 0) != 1) {
                throw new Exception("No feedback data available for the selected parameters.");
            }
            
            // Set up PDF with professional styling
            $this->setupPDFDocument();
            
            // Add pages based on level & content
            $this->addCoverPage($data, $level);
            $this->addExecutiveSummaryPage($data, $level);
            
            if ($level === 'department' && !empty($data['classes'])) {
                $this->addDepartmentClassesPage($data);
            } elseif ($level === 'class') {
                $this->addClassSubjectsPage($data);
                $this->addCOFeedbackPage($data, $level);
            } elseif ($level === 'faculty') {
                $this->addFacultySubjectsPage($data);
                $this->addCOFeedbackPage($data, $level);
                $this->addFacultyFeedbackPage($data, $level);
                $this->addQualitativeFeedbackPage($data, $level);
            } else {
                $this->addDetailedStudentRatingsPage($data, $level);
                $this->addCOFeedbackPage($data, $level);
                $this->addCESFeedbackPage($data, $level);
                $this->addFacultyFeedbackPage($data, $level);
                $this->addQualitativeFeedbackPage($data, $level);
            }
            $this->addAnalyticsPage($data, $level);
            
            // Generate output
            $filename = $this->generateFilename($level, $params);
            $filepath = $this->savePDF($filename);
            
            return $filepath;
            
        } catch (Exception $e) {
            throw new Exception("PDF generation failed: " . $e->getMessage());
        }
    }
    
    /**
     * Setup PDF document with professional settings
     */
    private function setupPDFDocument()
    {
        // Set page numbering
        $this->mpdf->aliasNbPages();
        
        // Set professional header
        $this->mpdf->SetHTMLHeader($this->getHeaderHTML());
        
        // Set professional footer
        $this->mpdf->SetHTMLFooter($this->getFooterHTML());
        
        // Add CSS styling
        $this->mpdf->WriteHTML($this->getCSS());
    }
    
    /**
     * Get professional header HTML
     */
    private function getHeaderHTML()
    {
        return '
            <div style="border-bottom: 3px solid ' . $this->colors['primary'] . '; padding-bottom: 8px; margin-bottom: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; vertical-align: middle;">
                            <h1 style="color: ' . $this->colors['primary'] . '; font-size: 16px; font-weight: bold; margin: 0; text-transform: uppercase;">
                                JNTUA COLLEGE OF ENGINEERING (AUTONOMOUS) ANANTHAPURAMU
                            </h1>
                            <p style="color: ' . $this->colors['muted'] . '; font-size: 9px; margin: 0; font-style: italic;">
                                Feedback Analysis Report
                            </p>
                        </td>
                        <td style="text-align: right; vertical-align: middle;">
                            <div style="color: ' . $this->colors['accent'] . '; font-size: 11px; font-weight: bold;">
                                {DATE j F Y}
                            </div>
                            <div style="color: ' . $this->colors['muted'] . '; font-size: 8px;">
                                Page {PAGENO} of {nbpg}
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        ';
    }
    
    /**
     * Get professional footer HTML
     */
    private function getFooterHTML()
    {
        return '
            <div style="border-top: 1px solid ' . $this->colors['border'] . '; padding-top: 8px; margin-top: 10px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="text-align: left; color: ' . $this->colors['muted'] . '; font-size: 8px;">
                            Confidential &bull; For Internal Academic Evaluation Only &bull; Document
                        </td>
                        <td style="text-align: right; color: ' . $this->colors['muted'] . '; font-size: 8px;">
                            &copy; ' . date('Y') . ' JNTUA College of Engineering Ananthapuramu
                        </td>
                    </tr>
                </table>
            </div>
        ';
    }
    
    /**
     * Get comprehensive CSS styling
     */
    private function getCSS()
    {
        return '
            <style>
                body {
                    font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
                    color: ' . $this->colors['text'] . ';
                    font-size: 10px;
                    line-height: 1.6;
                }
                
                /* Page styling */
                .page-title {
                    color: ' . $this->colors['primary'] . ';
                    font-size: 18px;
                    font-weight: bold;
                    text-align: center;
                    text-transform: uppercase;
                    margin: 20px 0 10px 0;
                    border-bottom: 2px solid ' . $this->colors['accent'] . ';
                    padding-bottom: 10px;
                }
                
                .section-title {
                    color: ' . $this->colors['secondary'] . ';
                    font-size: 14px;
                    font-weight: bold;
                    margin: 15px 0 10px 0;
                    padding: 8px 12px;
                    background-color: ' . $this->colors['light'] . ';
                    border-left: 4px solid ' . $this->colors['accent'] . ';
                }
                
                /* Table styling */
                .data-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 10px 0;
                    font-size: 9px;
                    page-break-inside: auto;
                }
                
                .data-table thead {
                    display: table-header-group;
                }
                
                .data-table tr {
                    page-break-inside: avoid;
                }
                
                .data-table th {
                    background-color: ' . $this->colors['primary'] . ';
                    color: white;
                    font-weight: bold;
                    padding: 8px 10px;
                    text-align: left;
                    border: 1px solid ' . $this->colors['border'] . ';
                }
                
                .data-table td {
                    padding: 8px 10px;
                    border: 1px solid ' . $this->colors['border'] . ';
                    vertical-align: middle;
                }
                
                .data-table tr:nth-child(even) {
                    background-color: ' . $this->colors['light'] . ';
                }
                
                /* Meta box styling */
                .meta-box {
                    background-color: ' . $this->colors['light'] . ';
                    border: 1px solid ' . $this->colors['border'] . ';
                    border-radius: 6px;
                    padding: 12px;
                    margin: 10px 0;
                }
                
                .meta-row {
                    display: flex;
                    justify-content: space-between;
                    margin: 5px 0;
                    padding: 5px 0;
                    border-bottom: 1px solid ' . $this->colors['border'] . ';
                }
                
                .meta-row:last-child {
                    border-bottom: none;
                }
                
                .meta-label {
                    font-weight: bold;
                    color: ' . $this->colors['secondary'] . ';
                    min-width: 150px;
                }
                
                /* KPI card styling */
                .kpi-container {
                    display: block;
                    margin: 15px 0;
                }
                
                .kpi-card {
                    background: linear-gradient(135deg, ' . $this->colors['light'] . ' 0%, white 100%);
                    border: 1px solid ' . $this->colors['border'] . ';
                    border-radius: 8px;
                    padding: 15px;
                    text-align: center;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                }
                
                .kpi-value {
                    font-size: 24px;
                    font-weight: bold;
                    color: ' . $this->colors['primary'] . ';
                    margin: 5px 0;
                }
                
                .kpi-label {
                    font-size: 9px;
                    color: ' . $this->colors['muted'] . ';
                    text-transform: uppercase;
                    margin: 0;
                }
                
                /* Badge styling */
                .badge {
                    display: inline-block;
                    padding: 4px 8px;
                    font-size: 8px;
                    font-weight: bold;
                    border-radius: 12px;
                    text-transform: uppercase;
                }
                
                .badge-success {
                    color: ' . $this->colors['success'] . ';
                    /* color: white; */
                }
                
                .badge-warning {
                    color: ' . $this->colors['warning'] . ';
                    /* color: white; */
                }
                
                .badge-danger {
                    color: ' . $this->colors['danger'] . ';
                    /* color: white; */
                }
                
                .badge-info {
                    color: ' . $this->colors['accent'] . ';
                    /* color: white; */
                }
                
                /* Rating bar styling */
                .rating-bar {
                    height: 8px;
                    background-color: ' . $this->colors['border'] . ';
                    border-radius: 4px;
                    overflow: hidden;
                    margin: 3px 0;
                }
                
                .rating-fill {
                    height: 100%;
                    border-radius: 4px;
                }
                
                .rating-fill.success {
                    background-color: ' . $this->colors['success'] . ';
                }
                
                .rating-fill.primary {
                    background-color: ' . $this->colors['secondary'] . ';
                }

                .rating-fill.info {
                    background-color: ' . $this->colors['accent'] . ';
                }

                .rating-fill.warning {
                    background-color: ' . $this->colors['warning'] . ';
                }
                
                .rating-fill.danger {
                    background-color: ' . $this->colors['danger'] . ';
                }
                
                /* Page break styling */
                .page-break {
                    page-break-after: always;
                }
                
                /* Text utilities */
                .text-center { text-align: center; }
                .text-right { text-align: right; }
                .text-bold { font-weight: bold; }
                .text-muted { color: ' . $this->colors['muted'] . '; }
                .text-small { font-size: 8px; }
                
                /* Quality box */
                .quality-box {
                    background-color: #f0f9ff;
                    border-left: 4px solid ' . $this->colors['accent'] . ';
                    padding: 12px;
                    margin: 10px 0;
                }
                
                .quality-box.warning {
                    background-color: #fffbeb;
                    border-left: 4px solid ' . $this->colors['warning'] . ';
                }
                
                .quality-box.danger {
                    background-color: #fef2f2;
                    border-left: 4px solid ' . $this->colors['danger'] . ';
                }
            </style>
        ';
    }
    
    /**
     * Add cover page
     */
    private function addCoverPage($data, $level)
    {
        $html = '
            <div style="text-align: center; padding: 50px 20px;">
                <h1 style="color: ' . $this->colors['primary'] . '; font-size: 28px; font-weight: bold; margin: 0 0 10px 0; text-transform: uppercase;">
                    Feedback Analysis Report
                </h1>
                <h2 style="color: ' . $this->colors['secondary'] . '; font-size: 16px; font-weight: normal; margin: 0 0 30px 0;">
                    Documentation
                </h2>
                
                <div style="max-width: 600px; margin: 0 auto; text-align: left;">
                    <div class="meta-box" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 15px;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 11px;">
                            <tr>
                                <td style="width: 35%; padding: 6px 0; font-weight: bold; color: #1e3a8a;">Report Level:</td>
                                <td style="padding: 6px 0; font-weight: bold;">' . ucfirst($level) . '-wise Feedback</td>
                            </tr>';
        
        // Add level-specific metadata
        if ($level === 'subject') {
            $meta = $data['meta'] ?? [];
            $deptName = htmlspecialchars($meta['dept_fullname'] ?? 'N/A');
            $className = htmlspecialchars($meta['classname'] ?? 'N/A');
            $subName = htmlspecialchars($meta['sub_fullname'] ?? '');
            $subCode = htmlspecialchars($meta['subcode'] ?? '');
            $facName = htmlspecialchars($meta['faculty_name'] ?? 'N/A');
            $acadYear = htmlspecialchars($meta['acad_year'] ?? 'N/A');
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Academic Year:</td><td>' . $acadYear . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Class:</td><td>' . $className . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Subject:</td><td>' . $subName . ' (' . $subCode . ')</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Assigned Faculty:</td><td>' . $facName . '</td></tr>';
        } elseif ($level === 'class') {
            $meta = $data['meta'] ?? [];
            $deptName = htmlspecialchars($meta['dept_fullname'] ?? 'N/A');
            $className = htmlspecialchars($meta['classname'] ?? 'N/A');
            $acadYear = htmlspecialchars($meta['acad_year'] ?? 'N/A');
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Class:</td><td>' . $className . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Academic Year:</td><td>' . $acadYear . '</td></tr>';
        } elseif ($level === 'faculty') {
            $fac = $data['faculty'] ?? [];
            $facName = htmlspecialchars($fac['faculty_name'] ?? 'N/A');
            $deptName = htmlspecialchars($fac['dept_fullname'] ?? 'N/A');
            $desig = htmlspecialchars($fac['designation'] ?? 'Faculty');
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Faculty Name:</td><td>' . $facName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Designation:</td><td>' . $desig . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Courses Taught:</td><td>' . count($data['subjects'] ?? []) . ' Courses</td></tr>';
        } elseif ($level === 'department') {
            $dept = $data['department'] ?? [];
            $deptName = htmlspecialchars($dept['dept_fullname'] ?? ($dept['dept_shortname'] ?? 'N/A'));
            $totalClasses = count($data['classes'] ?? []);
            
            $html .= '
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Department:</td><td>' . $deptName . '</td></tr>
                            <tr><td style="padding: 6px 0; font-weight: bold; color: #1e3a8a;">Classes Evaluated:</td><td>' . $totalClasses . ' Classes</td></tr>';
        }
        
        $html .= '
                        </table>
                    </div>
                </div>
                
                <div style="margin-top: 40px; color: ' . $this->colors['muted'] . '; font-size: 9px;">
                    <p>Generated on: ' . date('d F Y H:i:s') . '</p>
                    <p>Confidential Document for Internal Academic Evaluation</p>
                </div>
            </div>
        ';
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add executive summary page
     */
    private function addExecutiveSummaryPage($data, $level)
    {
        $html = '
            <div class="page-title">Executive Summary</div>
            
            <div class="kpi-container">
                ' . $this->getKPIHTML($data, $level) . '
            </div>
            
            <div class="section-title">Summary Statistics</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Metric</th>
                        <th>Value</th>
                        <th>Benchmark</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $this->getSummaryStatisticsHTML($data, $level) . '
                </tbody>
            </table>
        ';
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add detailed student ratings page
     */
    private function addDetailedStudentRatingsPage($data, $level)
    {
        $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
        $classId = intval($data['meta']['class_id'] ?? ($data['meta']['cls_id'] ?? 0));
        $coStudentData = $this->feedbackService->getSubjectStudentWiseRatings($subjectId, $classId);

        $cos = $coStudentData['cos'] ?? [];
        $students = $coStudentData['students'] ?? [];

        $html = '<div class="page-title">Detailed Student Ratings & Responses</div>';

        // 1. CO Mapping Legend
        $html .= '<div class="section-title">Course Outcomes (CO) Reference Legend</div>';
        $html .= '<table class="data-table" style="font-size: 8px;">
            <thead>
                <tr>
                    <th style="width: 15%; text-align: center;">CO Code</th>
                    <th style="width: 85%;">Course Outcome Statement</th>
                </tr>
            </thead>
            <tbody>';
        foreach ($cos as $co) {
            $html .= '<tr>
                <td style="text-align: center; font-weight: bold;">' . htmlspecialchars($co['co_label'] ?? ('CO' . ($co['co_number'] ?? ''))) . '</td>
                <td>' . htmlspecialchars($co['co_description'] ?? '') . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';

        // 2. Pivoted Student CO Matrix Table
        $html .= '<div class="section-title" style="margin-top: 15px;">Student Course Outcome (CO) Response Matrix</div>';
        $html .= '<table class="data-table" style="font-size: 8px;">
            <thead>
                <tr>
                    <th style="width: 14%;">Student Roll</th>
                    <th style="width: 22%;">Student Name</th>';
        foreach ($cos as $co) {
            $html .= '<th style="text-align: center;">' . htmlspecialchars($co['co_label'] ?? ('CO' . ($co['co_number'] ?? ''))) . '</th>';
        }
        $html .= '<th style="width: 10%; text-align: center;">Student Avg</th>
                  <th style="width: 12%; text-align: center;">Date</th>
                </tr>
            </thead>
            <tbody>';

        if (empty($students)) {
            $colspan = 4 + count($cos);
            $html .= '<tr><td colspan="' . $colspan . '" style="text-align: center; font-style: italic;">No student CO responses recorded.</td></tr>';
        } else {
            foreach ($students as $stu) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($stu['student_roll']) . '</td>
                    <td>' . htmlspecialchars($stu['student_name']) . '</td>';
                foreach ($cos as $coId => $coInfo) {
                    $rVal = $stu['ratings'][$coId] ?? null;
                    if ($rVal !== null && $rVal !== '') {
                        $badgeColor = $rVal >= 4 ? 'success' : ($rVal >= 3 ? 'warning' : 'danger');
                        $html .= '<td style="text-align: center;"><span class="badge badge-' . $badgeColor . '">' . intval($rVal) . '</span></td>';
                    } else {
                        $html .= '<td style="text-align: center; color: #888;">-</td>';
                    }
                }
                $avgVal = floatval($stu['average'] ?? 0);
                $avgBadge = $avgVal >= 4.0 ? 'success' : ($avgVal >= 3.0 ? 'warning' : 'danger');
                $html .= '<td style="text-align: center;"><span class="badge badge-' . $avgBadge . '">' . number_format($avgVal, 2) . '</span></td>';
                $html .= '<td style="text-align: center;">' . (!empty($stu['feedback_date']) ? date('Y-m-d', strtotime($stu['feedback_date'])) : 'N/A') . '</td>
                </tr>';
            }
        }
        $html .= '</tbody></table>';

        // 3. Student CES Ratings Section
        if (!empty($data['ces_feedback']['total_responses'])) {
            $html .= '<div class="section-title" style="margin-top: 15px;">Student Course End Survey (CES) Ratings (Q1 - Q16)</div>';
            $html .= '<table class="data-table" style="font-size: 7.5px;">
                <thead>
                    <tr>
                        <th style="width: 14%;">Student Roll</th>
                        <th style="width: 18%;">Student Name</th>';
            for ($qi = 1; $qi <= 16; $qi++) {
                $html .= '<th style="text-align: center;">Q' . $qi . '</th>';
            }
            $html .= '<th style="width: 10%; text-align: center;">Date</th>
                    </tr>
                </thead>
                <tbody>';

            $cesRatings = $this->getStudentCESRatingsPDF($level, $data);
            foreach ($cesRatings as $rating) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($rating['student_roll']) . '</td>
                    <td>' . htmlspecialchars($rating['student_name']) . '</td>';
                for ($qi = 1; $qi <= 16; $qi++) {
                    $html .= '<td style="text-align: center;">' . intval($rating["q$qi"] ?? 0) . '</td>';
                }
                $html .= '<td style="text-align: center;">' . htmlspecialchars($rating['response_date']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        // 4. Student Faculty Ratings Section
        if (!empty($data['faculty_evaluations']['total_evaluations'])) {
            $html .= '<div class="section-title" style="margin-top: 15px;">Student Faculty Evaluation Ratings (Q1 - Q19)</div>';
            $html .= '<table class="data-table" style="font-size: 7px;">
                <thead>
                    <tr>
                        <th style="width: 14%;">Student</th>';
            for ($qi = 1; $qi <= 19; $qi++) {
                $html .= '<th style="text-align: center;">Q' . $qi . '</th>';
            }
            $html .= '<th style="width: 10%; text-align: center;">Date</th>
                    </tr>
                </thead>
                <tbody>';

            $facRatings = $this->getStudentFacultyRatingsPDF($level, $data);
            $facSno = 1;
            foreach ($facRatings as $rating) {
                $html .= '<tr>
                    <td>Student-' . $facSno++ . '</td>';
                for ($qi = 1; $qi <= 19; $qi++) {
                    $html .= '<td style="text-align: center;">' . intval($rating["q$qi"] ?? 0) . '</td>';
                }
                $html .= '<td style="text-align: center;">' . htmlspecialchars($rating['response_date']) . '</td></tr>';
            }
            $html .= '</tbody></table>';
        }

        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Get student CO ratings for PDF
     */
    private function getStudentCORatingsPDF($coId, $level, $data)
    {
        $ratings = [];
        
        try {
            $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
            $classId = intval($data['meta']['class_id'] ?? ($data['meta']['cls_id'] ?? 0));
            
            if ($subjectId > 0) {
                $sql = "SELECT s.username AS roll_number, COALESCE(u.name, s.username) AS name, scr.rating, scr.feedback_date 
                        FROM student_co_feedback scr
                        JOIN students s ON scr.student_id = s.id
                        LEFT JOIN users u ON s.username = u.username
                        WHERE scr.co_id = ? AND scr.subject_id = ?";
                if ($classId > 0) {
                    $sql .= " AND scr.class_id = ?";
                }
                $sql .= " ORDER BY scr.feedback_date DESC";
                
                $stmt = $this->conn->prepare($sql);
                if ($classId > 0) {
                    $stmt->bind_param("iii", $coId, $subjectId, $classId);
                } else {
                    $stmt->bind_param("ii", $coId, $subjectId);
                }
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $ratings[] = [
                        'student_roll' => $row['roll_number'],
                        'student_name' => $row['name'],
                        'rating' => $row['rating'],
                        'response_date' => !empty($row['feedback_date']) ? date('Y-m-d', strtotime($row['feedback_date'])) : 'N/A'
                    ];
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
        
        return $ratings;
    }
    
    /**
     * Get student CES ratings for PDF
     */
    private function getStudentCESRatingsPDF($level, $data)
    {
        $ratings = [];
        
        try {
            $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
            
            if ($subjectId > 0) {
                $query = "SELECT 
                          sces.is_anonymous,
                          CASE WHEN sces.is_anonymous = 1 THEN 'Anonymous' ELSE s.username END AS roll_number,
                          CASE WHEN sces.is_anonymous = 1 THEN 'Anonymous' ELSE COALESCE(u.name, s.username) END AS name,
                          sces.ces_q1, sces.ces_q2, sces.ces_q3, sces.ces_q4, sces.ces_q5, sces.ces_q6, sces.ces_q7,
                          sces.ces_q8, sces.ces_q9, sces.ces_q10, sces.ces_q11, sces.ces_q12, sces.ces_q13, sces.ces_q14, sces.ces_q15, sces.ces_q16,
                          sces.submitted_at
                          FROM student_ces_feedback sces
                          JOIN students s ON sces.student_id = s.id
                          LEFT JOIN users u ON s.username = u.username
                          WHERE sces.subject_id = ?
                          ORDER BY sces.is_anonymous ASC, s.username ASC, sces.submitted_at DESC";
                
                $stmt = $this->conn->prepare($query);
                $stmt->bind_param("i", $subjectId);
                $stmt->execute();
                $result = $stmt->get_result();
                
                while ($row = $result->fetch_assoc()) {
                    $item = [
                        'student_roll' => $row['roll_number'],
                        'student_name' => $row['name'],
                        'response_date' => !empty($row['submitted_at']) ? date('Y-m-d', strtotime($row['submitted_at'])) : 'N/A'
                    ];
                    for ($qi = 1; $qi <= 16; $qi++) {
                        $item["q$qi"] = $row["ces_q$qi"] ?? 0;
                    }
                    $ratings[] = $item;
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            // Return empty array on error
        }
        
        return $ratings;
    }
    
    /**
     * Get student faculty ratings for PDF
     */
    private function getStudentFacultyRatingsPDF($level, $data)
    {
        $ratings = [];
        
        try {
            $subjectId = intval($data['meta']['subject_id'] ?? ($data['meta']['sub_id'] ?? 0));
            $facultyId = intval($data['faculty']['id'] ?? ($data['meta']['faculty_id'] ?? 0));
            
            // If viewing subject level, retrieve faculty from mapped faculties list if not yet set
            if ($facultyId <= 0 && !empty($data['meta']['faculties'])) {
                $facultyId = intval($data['meta']['faculties'][0]['faculty_id'] ?? 0);
            }

            $query = "SELECT 
                      s.username AS roll_number, 
                      COALESCE(u.name, s.username) AS name,
                      sff.fac_q1, sff.fac_q2, sff.fac_q3, sff.fac_q4, sff.fac_q5, sff.fac_q6, sff.fac_q7,
                      sff.fac_q8, sff.fac_q9, sff.fac_q10, sff.fac_q11, sff.fac_q12, sff.fac_q13, sff.fac_q14, 
                      sff.fac_q15, sff.fac_q16, sff.fac_q17, sff.fac_q18, sff.fac_q19,
                      sff.submitted_at
                      FROM student_faculty_feedback sff
                      JOIN students s ON sff.student_id = s.id
                      LEFT JOIN users u ON s.username = u.username
                      WHERE 1=1";

            $params = [];
            $types = "";

            if ($subjectId > 0) {
                $query .= " AND sff.subject_id = ?";
                $params[] = $subjectId;
                $types .= "i";
            }
            if ($facultyId > 0) {
                $query .= " AND sff.faculty_id = ?";
                $params[] = $facultyId;
                $types .= "i";
            }
            $query .= " ORDER BY sff.submitted_at DESC";

            $stmt = $this->conn->prepare($query);
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $result = $stmt->get_result();

            while ($row = $result->fetch_assoc()) {
                $item = [
                    'student_roll' => $row['roll_number'],
                    'student_name' => $row['name'],
                    'response_date' => !empty($row['submitted_at']) ? date('Y-m-d', strtotime($row['submitted_at'])) : 'N/A'
                ];
                for ($qi = 1; $qi <= 19; $qi++) {
                    $item["q$qi"] = intval($row["fac_q$qi"] ?? 0);
                }
                $ratings[] = $item;
            }
            $stmt->close();
        } catch (Exception $e) {
            // Logging
        }
        
        return $ratings;
    }
    
    /**
     * Get KPI HTML (4 rows instead of horizontal layout)
     */
    private function getKPIHTML($data, $level)
    {
        $kpiData = $this->getKPIData($data, $level);
        $html = '<table style="width: 100%; border-collapse: collapse; margin-bottom: 15px;">';
        
        // Display KPIs in clean table rows for reliable mPDF rendering
        foreach ($kpiData as $kpi) {
            $color = $this->getKPIColor($kpi['value'], $kpi['type']);
            $html .= '
                <tr>
                    <td style="padding: 8px 12px; border-bottom: 1px solid ' . $this->colors['border'] . '; font-weight: bold; color: ' . $this->colors['text'] . '; width: 50%;">
                        ' . htmlspecialchars($kpi['label']) . ':
                    </td>
                    <td style="padding: 8px 12px; border-bottom: 1px solid ' . $this->colors['border'] . '; font-weight: bold; font-size: 14px; text-align: right; color: ' . $color . '; width: 50%;">
                        ' . htmlspecialchars($kpi['value']) . '
                    </td>
                </tr>
            ';
        }
        $html .= '</table>';
        
        return $html;
    }
    
    /**
     * Get summary statistics HTML
     */
    private function getSummaryStatisticsHTML($data, $level)
    {
        $stats = $this->getSummaryStatistics($data, $level);
        $html = '';
        
        foreach ($stats as $stat) {
            $statusColor = $stat['status'] === 'Above Target' ? 'success' : 
                          ($stat['status'] === 'Below Target' ? 'danger' : 'warning');
            $html .= '
                <tr>
                    <td class="text-bold">' . $stat['metric'] . '</td>
                    <td>' . $stat['value'] . '</td>
                    <td>' . $stat['benchmark'] . '</td>
                    <td><span class="badge badge-' . $statusColor . '">' . $stat['status'] . '</span></td>
                </tr>
            ';
        }
        
        return $html;
    }
    
    /**
     * Add CO feedback page
     */
    private function addCOFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Course Outcome (CO) Feedback Analysis</div>
            
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 8%;">CO#</th>
                        <th style="width: 35%;">CO Description</th>
                        <th style="width: 12%; text-align: center;">Avg Rating</th>
                        <th style="width: 10%; text-align: center;">Responses</th>
                        <th style="width: 35%; text-align: center;">Rating Distribution</th>
                    </tr>
                </thead>
                <tbody>
        ';
        
        $coFeedback = $data['co_feedback'] ?? [];
        foreach ($coFeedback as $co) {
            $rating = $co['average_rating'];
            $ratingColor = $rating >= 4.0 ? 'success' : ($rating >= 3.0 ? 'warning' : 'danger');
            
            $html .= '
                <tr>
                    <td class="text-bold text-center">CO' . $co['co_number'] . '</td>
                    <td>' . htmlspecialchars($co['co_description']) . '</td>
                    <td class="text-center">
                        <span class="badge badge-' . $ratingColor . '">' . number_format($rating, 2) . '</span>
                    </td>
                    <td class="text-center">' . $co['total_responses'] . '</td>
                    <td>
                        <table style="width: 100%; border-collapse: collapse; font-size: 7.5px;">
                            <tr>
                                <td style="width: 18%; border: none; padding: 1px 2px;">5★: ' . ($co['count_5'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">4★: ' . ($co['count_4'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">3★: ' . ($co['count_3'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">2★: ' . ($co['count_2'] ?? 0) . '</td>
                                <td style="width: 18%; border: none; padding: 1px 2px;">1★: ' . ($co['count_1'] ?? 0) . '</td>
                            </tr>
                            <tr>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill success" style="width: ' . $this->getPercentage($co['count_5'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill primary" style="width: ' . $this->getPercentage($co['count_4'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill info" style="width: ' . $this->getPercentage($co['count_3'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill warning" style="width: ' . $this->getPercentage($co['count_2'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                                <td style="border: none; padding: 1px 2px;">
                                    <div class="rating-bar"><div class="rating-fill danger" style="width: ' . $this->getPercentage($co['count_1'] ?? 0, $co['total_responses']) . '%;"></div></div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            ';
        }
        
        $html .= '
                </tbody>
            </table>
        ';
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add CES feedback page
     */
    private function addCESFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Course End Survey (CES) - 16 Evaluation Parameters</div>
        ';
        
        if (empty($data['ces_feedback']['total_responses'])) {
            $html .= '<p class="text-muted">No CES feedback data available for this selection.</p>';
        } else {
            $cesQuestions = FeedbackService::getCesQuestions();
            $cesAverages = $data['ces_feedback']['averages'] ?? [];
            
            $html .= '
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Section</th>
                            <th style="width: 10%;">Parameter</th>
                            <th style="width: 55%;">Evaluation Statement</th>
                            <th style="width: 15%; text-align: center;">Avg Rating</th>
                        </tr>
                    </thead>
                    <tbody>
            ';
            
            foreach ($cesQuestions as $section => $questions) {
                foreach ($questions as $qKey => $qText) {
                    $avg = $cesAverages[$qKey] ?? 0;
                    $ratingColor = $avg >= 4.0 ? 'success' : ($avg >= 3.0 ? 'warning' : 'danger');
                    
                    $html .= '
                        <tr>
                            <td><small class="text-muted">' . htmlspecialchars($section) . '</small></td>
                            <td class="text-bold">' . strtoupper($qKey) . '</td>
                            <td>' . htmlspecialchars($qText) . '</td>
                            <td class="text-center">
                                <span class="badge badge-' . $ratingColor . '">' . number_format($avg, 2) . '</span>
                            </td>
                        </tr>
                    ';
                }
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add faculty feedback page
     */
    private function addFacultyFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Student Feedback on Faculty - 19 OBE Parameters</div>
        ';
        
        if (empty($data['faculty_evaluations']['total_evaluations'])) {
            $html .= '<p class="text-muted">No faculty evaluation data available for this selection.</p>';
        } else {
            $facQuestions = FeedbackService::getFacultyQuestions();
            $facAverages = $data['faculty_evaluations']['averages'] ?? [];
            
            $html .= '
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Domain</th>
                            <th style="width: 10%;">Parameter</th>
                            <th style="width: 55%;">Evaluation Statement</th>
                            <th style="width: 15%; text-align: center;">Avg Rating</th>
                        </tr>
                    </thead>
                    <tbody>
            ';
            
            foreach ($facQuestions as $domain => $questions) {
                foreach ($questions as $qKey => $qText) {
                    $avg = $facAverages[$qKey] ?? 0;
                    $ratingColor = $avg >= 4.0 ? 'success' : ($avg >= 3.0 ? 'warning' : 'danger');
                    
                    $html .= '
                        <tr>
                            <td><small class="text-muted">' . htmlspecialchars($domain) . '</small></td>
                            <td class="text-bold">' . strtoupper($qKey) . '</td>
                            <td>' . htmlspecialchars($qText) . '</td>
                            <td class="text-center">
                                <span class="badge badge-' . $ratingColor . '">' . number_format($avg, 2) . '</span>
                            </td>
                        </tr>
                    ';
                }
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add department classes overview page
     */
    private function addDepartmentClassesPage($data)
    {
        $html = '<div class="page-title">Class-Wise Feedback Performance Overview</div>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 35%;">Class Name</th>
                    <th style="width: 15%;">Academic Year</th>
                    <th style="width: 10%; text-align: center;">Subjects</th>
                    <th style="width: 12%; text-align: center;">Enrolled</th>
                    <th style="width: 13%; text-align: center;">Responses</th>
                    <th style="width: 10%; text-align: center;">Class Avg</th>
                </tr>
            </thead>
            <tbody>';
        $sno = 1;
        foreach (($data['classes'] ?? []) as $cls) {
            $stats = $cls['stats'] ?? [];
            $avg = floatval($stats['overall_avg'] ?? 0);
            $badge = $avg >= 3.5 ? 'success' : ($avg >= 2.5 ? 'warning' : 'danger');
            $html .= '<tr>
                <td style="text-align: center;">' . ($sno++) . '</td>
                <td class="text-bold">' . htmlspecialchars($cls['classname'] ?? '') . '</td>
                <td>' . htmlspecialchars($cls['acad_year'] ?? '') . '</td>
                <td style="text-align: center;">' . intval($stats['total_subjects'] ?? 0) . '</td>
                <td style="text-align: center;">' . intval($stats['total_students'] ?? 0) . '</td>
                <td style="text-align: center;">' . intval($stats['total_responded'] ?? 0) . ' (' . ($stats['response_rate'] ?? 0) . '%)</td>
                <td style="text-align: center;"><span class="badge badge-' . $badge . '">' . number_format($avg, 2) . '</span></td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    /**
     * Add class subjects overview page
     */
    private function addClassSubjectsPage($data)
    {
        $html = '<div class="page-title">Subject Performance Overview</div>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 15%;">Sub Code</th>
                    <th style="width: 35%;">Subject Full Name</th>
                    <th style="width: 25%;">Assigned Faculty</th>
                    <th style="width: 10%; text-align: center;">Responses</th>
                    <th style="width: 10%; text-align: center;">Average (1-5)</th>
                </tr>
            </thead>
            <tbody>';
        $sno = 1;
        foreach (($data['subjects'] ?? []) as $sub) {
            $avg = floatval($sub['summary']['overall_avg'] ?? ($sub['summary']['overall_co_avg'] ?? 0));
            $badge = $avg >= 3.5 ? 'success' : ($avg >= 2.5 ? 'warning' : 'danger');
            $html .= '<tr>
                <td style="text-align: center;">' . ($sno++) . '</td>
                <td class="text-bold">' . htmlspecialchars($sub['subcode'] ?? '') . '</td>
                <td>' . htmlspecialchars($sub['sub_fullname'] ?? '') . '</td>
                <td>' . htmlspecialchars($sub['faculty_names'] ?? 'N/A') . '</td>
                <td style="text-align: center;">' . intval($sub['summary']['total_ratings_count'] ?? 0) . '</td>
                <td style="text-align: center;"><span class="badge badge-' . $badge . '">' . number_format($avg, 2) . '</span></td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    /**
     * Add faculty assigned subjects overview page
     */
    private function addFacultySubjectsPage($data)
    {
        $html = '<div class="page-title">Assigned Courses Feedback Overview</div>';
        $html .= '<table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 25%;">Class</th>
                    <th style="width: 15%;">Subject Code</th>
                    <th style="width: 40%;">Subject Name</th>
                    <th style="width: 15%; text-align: center;">Academic Year</th>
                </tr>
            </thead>
            <tbody>';
        $sno = 1;
        foreach (($data['subjects'] ?? []) as $sub) {
            $html .= '<tr>
                <td style="text-align: center;">' . ($sno++) . '</td>
                <td>' . htmlspecialchars($sub['classname'] ?? '') . '</td>
                <td class="text-bold">' . htmlspecialchars($sub['subcode'] ?? '') . '</td>
                <td>' . htmlspecialchars($sub['sub_fullname'] ?? '') . '</td>
                <td style="text-align: center;">' . htmlspecialchars($sub['acad_year'] ?? '') . '</td>
            </tr>';
        }
        $html .= '</tbody></table>';
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }

    
    /**
     * Add qualitative feedback page
     */
    private function addQualitativeFeedbackPage($data, $level)
    {
        $html = '
            <div class="page-title">Qualitative Feedback & Remarks</div>
        ';
        
        $hasData = false;
        
        // CES remarks
        if (!empty($data['ces_feedback']['remarks'])) {
            $hasData = true;
            $html .= '
                <div class="section-title">Course End Survey (CES) Remarks</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 15%;">Student</th>
                            <th style="width: 28%;">Useful Aspects</th>
                            <th style="width: 28%;">Improvement Topics</th>
                            <th style="width: 29%;">Suggestions</th>
                        </tr>
                    </thead>
                    <tbody>
            ';
            
            foreach ($data['ces_feedback']['remarks'] as $remark) {
                $html .= '
                        <tr>
                            <td class="text-bold">' . htmlspecialchars($remark['student_roll'] ?? 'Anonymous') . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['useful_aspects'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['improvement_topics'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['suggestions'])) . '</td>
                        </tr>
                ';
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        // Faculty remarks
        if (!empty($data['faculty_evaluations']['remarks'])) {
            $hasData = true;
            $html .= '
                <div class="section-title">Faculty Evaluation Remarks</div>
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Student</th>
                            <th style="width: 26%;">Strengths</th>
                            <th style="width: 26%;">Improvement Areas</th>
                            <th style="width: 28%;">Additional Comments</th>
                        </tr>
                    </thead>
                    <tbody>
            ';

            $facSno = 1;
            foreach ($data['faculty_evaluations']['remarks'] as $remark) {
                $subCode = $remark['subcode'] ?? ($data['meta']['subcode'] ?? '');
                $subName = $remark['sub_fullname'] ?? ($data['meta']['sub_fullname'] ?? '');
                $subLabel = (!empty($subCode) || !empty($subName)) ? trim("$subCode - $subName", " -") : 'N/A';
                                
                $html .= '
                        <tr>
                            <td class="text-bold">Student-' . $facSno++ . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['faculty_strengths'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['improvement_areas'])) . '</td>
                            <td>' . nl2br(htmlspecialchars($remark['additional_comments'])) . '</td>
                        </tr>
                ';
            }
            
            $html .= '
                    </tbody>
                </table>
            ';
        }
        
        if (!$hasData) {
            $html .= '<p class="text-muted">No qualitative feedback available for this selection.</p>';
        }
        
        $this->mpdf->WriteHTML($html);
        $this->mpdf->AddPage();
    }
    
    /**
     * Add analytics page
     */
    private function addAnalyticsPage($data, $level)
    {
        $html = '
            <div class="page-title">Analytics & Insights</div>
            
            <div class="section-title">Rating Distribution Analysis</div>
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rating</th>
                        <th>Count</th>
                        <th>Percentage</th>
                        <th>Cumulative %</th>
                    </tr>
                </thead>
                <tbody>
                    ' . $this->getRatingDistributionHTML($data) . '
                </tbody>
            </table>
            
            <div class="section-title">Key Insights</div>
            <div class="quality-box">
                <p><strong>Response Rate:</strong> ' . $this->getResponseRate($data) . '</p>
                <p><strong>Overall Performance:</strong> ' . $this->getOverallPerformance($data) . '</p>
                <p><strong>Areas of Excellence:</strong> ' . $this->getExcellenceAreas($data) . '</p>
                <p><strong>Areas for Improvement:</strong> ' . $this->getImprovementAreas($data) . '</p>
            </div>
        ';
        
        $this->mpdf->WriteHTML($html);
    }
    
    /**
     * Helper methods
     */
    private function fetchFeedbackData($level, $params)
    {
        switch ($level) {
            case 'subject':
                return $this->feedbackService->getSubjectFeedback(
                    intval($params['sub_id'] ?? 0),
                    intval($params['cls_id'] ?? 0)
                );
            case 'class':
                return $this->feedbackService->getClassFeedback(
                    intval($params['cls_id'] ?? 0)
                );
            case 'faculty':
                return $this->feedbackService->getFacultyFeedback(
                    intval($params['fac_id'] ?? 0),
                    intval($params['cls_id'] ?? 0)
                );
            case 'department':
                return $this->feedbackService->getDepartmentFeedback(
                    intval($params['dept_id'] ?? 0)
                );
            default:
                throw new Exception("Invalid feedback level: $level");
        }
    }
    
    private function getKPIData($data, $level)
    {
        $kpiData = [];
        
        if ($level === 'subject') {
            $kpiData[] = ['label' => 'Total Enrolled', 'value' => $data['total_enrolled'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Responses', 'value' => $data['total_responded'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Response Rate', 'value' => ($data['response_rate'] ?? 0) . '%', 'type' => 'percentage'];
            $kpiData[] = ['label' => 'Overall Rating', 'value' => number_format($data['summary']['overall_avg'] ?? 0, 2), 'type' => 'rating'];
        } elseif ($level === 'class') {
            $kpiData[] = ['label' => 'Total Students', 'value' => $data['total_students'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Responses', 'value' => $data['total_responded'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Response Rate', 'value' => ($data['response_rate'] ?? 0) . '%', 'type' => 'percentage'];
            $kpiData[] = ['label' => 'Class Average', 'value' => number_format($data['summary']['overall_avg'] ?? 0, 2), 'type' => 'rating'];
        } elseif ($level === 'faculty') {
            $kpiData[] = ['label' => 'Courses Assigned', 'value' => count($data['subjects'] ?? []), 'type' => 'count'];
            $kpiData[] = ['label' => 'CO Overall Average', 'value' => number_format($data['summary']['overall_co_avg'] ?? 0, 2), 'type' => 'rating'];
            $kpiData[] = ['label' => 'Student Evaluations', 'value' => $data['faculty_evaluations']['total_evaluations'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Appraisal Score (1-5)', 'value' => !empty($data['faculty_evaluations']['avg_score']) ? number_format($data['faculty_evaluations']['avg_score'], 2) : 'N/A', 'type' => 'rating'];
        } elseif ($level === 'department') {
            $kpiData[] = ['label' => 'Total Classes', 'value' => $data['summary']['total_classes'] ?? count($data['classes'] ?? []), 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Students', 'value' => $data['summary']['total_students'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Total Responses', 'value' => $data['summary']['total_responses'] ?? 0, 'type' => 'count'];
            $kpiData[] = ['label' => 'Department Average', 'value' => number_format($data['summary']['overall_avg'] ?? 0, 2), 'type' => 'rating'];
        }
        
        return $kpiData;
    }
    
    private function getSummaryStatistics($data, $level)
    {
        $stats = [];
        $overallAvg = $data['summary']['overall_avg'] ?? ($data['summary']['overall_co_avg'] ?? 0);
        $responseRate = $data['response_rate'] ?? 0;
        
        $stats[] = [
            'metric' => 'Overall Rating',
            'value' => number_format($overallAvg, 2),
            'benchmark' => '≥ 3.5',
            'status' => $overallAvg >= 3.5 ? 'Above Target' : ($overallAvg >= 3.0 ? 'On Target' : 'Below Target')
        ];
        
        $stats[] = [
            'metric' => 'Response Rate',
            'value' => number_format($responseRate, 1) . '%',
            'benchmark' => '≥ 75%',
            'status' => $responseRate >= 75 ? 'Above Target' : ($responseRate >= 60 ? 'On Target' : 'Below Target')
        ];
        
        return $stats;
    }
    
    private function getKPIColor($value, $type)
    {
        if ($type === 'percentage') {
            if ($value >= 80) return $this->colors['success'];
            if ($value >= 60) return $this->colors['warning'];
            return $this->colors['danger'];
        } elseif ($type === 'rating') {
            if ($value >= 4.0) return $this->colors['success'];
            if ($value >= 3.0) return $this->colors['warning'];
            return $this->colors['danger'];
        }
        return $this->colors['accent'];
    }
    
    private function getRatingDistributionHTML($data)
    {
        $distribution = $this->calculateRatingDistribution($data);
        $total = array_sum($distribution);
        $cumulative = 0;
        $html = '';
        
        foreach ($distribution as $rating => $count) {
            $percentage = $total > 0 ? ($count / $total) * 100 : 0;
            $cumulative += $percentage;
            
            $html .= '
                <tr>
                    <td class="text-center">' . $rating . '★</td>
                    <td class="text-center">' . $count . '</td>
                    <td class="text-center">' . number_format($percentage, 1) . '%</td>
                    <td class="text-center">' . number_format($cumulative, 1) . '%</td>
                </tr>
            ';
        }
        
        return $html;
    }
    
    private function calculateRatingDistribution($data)
    {
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        
        $coFeedback = $data['co_feedback'] ?? [];
        foreach ($coFeedback as $co) {
            $distribution[5] += $co['count_5'] ?? 0;
            $distribution[4] += $co['count_4'] ?? 0;
            $distribution[3] += $co['count_3'] ?? 0;
            $distribution[2] += $co['count_2'] ?? 0;
            $distribution[1] += $co['count_1'] ?? 0;
        }
        
        return $distribution;
    }
    
    private function getPercentage($value, $total)
    {
        return $total > 0 ? ($value / $total) * 100 : 0;
    }
    
    private function getResponseRate($data)
    {
        $rate = $data['response_rate'] ?? 0;
        return $rate >= 75 ? 'Excellent (≥75%)' : ($rate >= 60 ? 'Good (60-74%)' : 'Needs Improvement (<60%)');
    }
    
    private function getOverallPerformance($data)
    {
        $avg = $data['summary']['overall_avg'] ?? ($data['summary']['overall_co_avg'] ?? 0);
        return $avg >= 4.0 ? 'Outstanding (≥4.0)' : ($avg >= 3.0 ? 'Satisfactory (3.0-3.9)' : 'Below Expectations (<3.0)');
    }
    
    private function getExcellenceAreas($data)
    {
        $areas = [];
        $coFeedback = $data['co_feedback'] ?? [];
        
        foreach ($coFeedback as $co) {
            if ($co['average_rating'] >= 4.0) {
                $areas[] = 'CO' . $co['co_number'] . ' (' . number_format($co['average_rating'], 2) . ')';
            }
        }
        
        return !empty($areas) ? implode(', ', $areas) : 'None identified';
    }
    
    private function getImprovementAreas($data)
    {
        $areas = [];
        $coFeedback = $data['co_feedback'] ?? [];
        
        foreach ($coFeedback as $co) {
            if ($co['average_rating'] < 3.0) {
                $areas[] = 'CO' . $co['co_number'] . ' (' . number_format($co['average_rating'], 2) . ')';
            }
        }
        
        return !empty($areas) ? implode(', ', $areas) : 'None identified';
    }
    
    private function generateFilename($level, $params)
    {
        $timestamp = date("Ymd_His");
        $identifier = '';
        
        switch ($level) {
            case 'subject':
                $identifier = "subject_" . ($params['sub_id'] ?? 'unknown');
                break;
            case 'class':
                $identifier = "class_" . ($params['cls_id'] ?? 'unknown');
                break;
            case 'faculty':
                $identifier = "faculty_" . ($params['fac_id'] ?? 'unknown');
                break;
            case 'department':
                $identifier = "department_" . ($params['dept_id'] ?? 'unknown');
                break;
        }
        
        return "feedback_{$identifier}_{$timestamp}.pdf";
    }
    
    private function savePDF($filename)
    {
        $filepath = __DIR__ . '/../uploads/' . $filename;
        $this->mpdf->Output($filepath, \Mpdf\Output\Destination::FILE);
        return $filepath;
    }
    
    public function __destruct()
    {
        // Cleanup
    }
}
?>