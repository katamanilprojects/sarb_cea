<?php
session_start();

if (empty($_POST['sub_id']) || empty($_POST['secretcode']) || $_POST['secretcode'] != $_SESSION['secretcode']) {
    die("Invalid Request"); // Handle invalid requests
}

if (!empty($_SESSION['facid'])) {
    $facid = $_SESSION['facid'];
} elseif (!empty($_POST['facid'])) {
    $facid = $_POST['facid'];
}

$sub_id = $_POST['sub_id'];

require_once("faculty.class.php");
require_once __DIR__ . '/vendor/autoload.php'; // Ensure mPDF is loaded

$facultyObj = new Faculty();
$start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
$end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : null;

$attendanceData = $facultyObj->getDetailedAttendanceBySubject($sub_id, $start_date, $end_date);

if (!empty($attendanceData['data'])) {
    $dateHours = array_keys($attendanceData['data'][0]['attendance']);
    usort($dateHours, function ($a, $b) {
        return strtotime($a) - strtotime($b);
    });

    // Extract Starting and Ending Dates if not provided
    if (empty($start_date)) {
        $start_date = explode('-', $dateHours[0]);
        $start_date = $start_date[0] . '-' . $start_date[1] . '-' . $start_date[2];
    }
    if (empty($end_date)) {
        $end_date = explode('-', end($dateHours));
        $end_date = $end_date[0] . '-' . $end_date[1] . '-' . $end_date[2];
    }

    $studentsAttendance = [];
    $totalClassesHeld = count($dateHours);
    foreach ($attendanceData['data'] as $student) {
        $totalClassesAttended = 0;
        $totalClassesHeld = 0;
        foreach ($dateHours as $dateHour) {
            if ($student['attendance'][$dateHour]) {
                $status = $student['attendance'][$dateHour];
                $totalClassesHeld++;
                if (strtoupper($status) == 'P') {
                    $totalClassesAttended++;
                }
            } else {
                $status = 'N/A';
            }
        }
        $totalClassesAttended = $student['total_present'];
        $totalClassesHeld = $student['total_classes'];

        $attendancePercentage = ($totalClassesHeld > 0) ? round(($totalClassesAttended / $totalClassesHeld) * 100, 2) : 0;

        // Store the totals for the student
        $studentsAttendance[$student['username']] = [
            'total_held' => $totalClassesHeld,
            'total_attended' => $totalClassesAttended,
            'percentage' => $attendancePercentage
        ];
    }

    // Format dates to dd-mm-YYYY
    $start_date_formatted = date('d-m-Y', strtotime($start_date));
    $end_date_formatted = date('d-m-Y', strtotime($end_date));

    if (!empty($facid)) {
        // Fetch and process Class, Subject, and Faculty details
        $details = $facultyObj->getClassSubjectFacultyDetails($sub_id, $facid);
    } else {
        require_once("hod.class.php");
        $hodObj = new HOD();
        $details = $hodObj->getClsSubFacBySubID($sub_id);
    }
    $class_full = $details['data']['classname'] ?? '';
    $subject = $details['data']['sub_fullname'] ?? '';

    // Centralized Academic Settings & Regulatory Scoping
    require_once __DIR__ . '/services/SettingsService.php';
    $settingsService = \Services\SettingsService::getInstance();

    $regulation = 'R23';
    $dbConn = DBCredentials::getInstance()->getConnection();
    if ($rStmt = $dbConn->prepare("SELECT c.reg FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.id = ?")) {
        $rStmt->bind_param("i", $sub_id);
        if ($rStmt->execute()) {
            $rStmt->bind_result($foundReg);
            if ($rStmt->fetch() && !empty($foundReg)) {
                $regulation = strtoupper(trim($foundReg));
            }
        }
        $rStmt->close();
    }

    $midBetterWeight = (float)$settingsService->get('theory_mid_better_weight', $regulation, 0.80);
    $midLesserWeight = (float)$settingsService->get('theory_mid_lesser_weight', $regulation, 0.20);
    $betterWeightPct = round($midBetterWeight * 100);
    $lesserWeightPct = round($midLesserWeight * 100);

    $overallDirectPct = round((float)$settingsService->get('overall_direct_weight', $regulation, 0.80) * 100);
    $overallIndirectPct = round((float)$settingsService->get('overall_indirect_weight', $regulation, 0.20) * 100);
    $directCiaPct = round((float)$settingsService->get('attainment_direct_cia_weight', $regulation, 0.30) * 100);
    $directSeePct = round((float)$settingsService->get('attainment_direct_see_weight', $regulation, 0.70) * 100);

    // Extract Class, Semester, and Branch from the class string
    $class_parts = explode(' - ', $class_full);
    $semester = trim($class_parts[2]);
    function getBranch($text) {
        preg_match_all('/\(([^)]+)\)/', $text, $matches);

        if (count($matches[1]) === 0) {
            return ''; // no () found
        } elseif (count($matches[1]) === 1) {
            return $matches[1][0]; // only one set, e.g., ECE
        } else {
            // two or more sets, join last two
            $last  = array_pop($matches[1]);
            $secondLast = array_pop($matches[1]);
            return $secondLast . ' (' . $last . ')';
        }
    }
    $branch = getBranch($class_parts[0]);

    $pos = strpos($class_parts[0], '(');
    if ($pos !== false) {
        $class = trim(substr($class_parts[0], 0, $pos)) . ' - ' . trim($class_parts[1]);
    } else {
        $class = trim($class_parts[0]) . ' - ' . trim($class_parts[1]);
    }

    $faculty = $details['data']['faculty_name'] ?? 'N/A';
    $acadYear = $details['data']['acad_year'] ?? date('Y') . '-' . (date('Y') + 1); //date('Y') . '-' . (date('Y') + 1);  // Assuming academic year is current year

    // Initialize mPDF with reduced margins and smaller font size
    $mpdf = new \Mpdf\Mpdf([
        'margin_left' => 8,
        'margin_right' => 8,
        'margin_top' => 36,
        'margin_bottom' => 12,
        'default_font_size' => 8
    ]);

    // Define header for all pages
    $header = '
    <table cellpadding="10" cellspacing="0" style="width:100%; border-collapse:collapse;">
        <tr>
            <td style="text-align:left; width:25%; font-size:12px; line-height: 18px; padding-bottom:0px;">
                <strong>Class:</strong> ' . $class . '<br>
                <strong>Semester:</strong> ' . $semester . '<br>
                <strong>Branch:</strong> ' . $branch . '
            </td>
            <td colspan=2 style="text-align:center; width:50%; font-size:24px; padding-bottom:0px;">
                <strong>Attendance</strong>
            </td>
            <td style="text-align:right; width:25%; font-size:12px; line-height: 18px; padding-bottom:0px;">
                <strong>Acad. Year:</strong> ' . $acadYear . '<br>
                <strong>Starting:</strong> ' . $start_date_formatted . '<br>
                <strong>Ending:</strong> ' . $end_date_formatted . '
            </td>
        </tr>
        <tr>
            <td colspan=2 style="text-align:left; width:50%; font-size:12px;line-height: 18px; line-height: 18px; padding-top:0px;">
                <strong>Subject:</strong> ' . $subject . '
            </td>
            <td colspan=2 style="text-align:right; width:50%; font-size:12px; line-height: 18px; padding-top:0px;">
                <strong>Teacher:</strong> ' . $faculty . '
            </td>
        </tr>
    </table>
    <hr style="margin:0px; padding:0px;"><br>';

    // Set the header for all pages
    $mpdf->SetHTMLHeader($header, 0);

    // Calculate the number of pages required based on dates and students per page
    $studentsPerPage = 36;
    $totalDates = count($dateHours);
    $totalStudents = count($attendanceData['data']);
    $totalStudentPages = ceil($totalStudents / $studentsPerPage);

    // Loop through each set of students
    $sno = 1;
    for ($studentPage = 0; $studentPage < $totalStudentPages; $studentPage++) {
        $datesPerPage = 8;
        $studentStart = $studentPage * $studentsPerPage;
        $studentEnd = min($studentStart + $studentsPerPage, $totalStudents);

        $noofpages = 0;
        if ($totalDates < 8) {
            $noofpages++;
        } else {
            $i = 0;
            while ($totalDates > (($i * 12) + 8)) {
                $noofpages++;
                $i++;
            }
        }

        if ($totalDates <= 4) {
            $noofpages = 0;
        }
        if ($totalDates > 4 && $totalDates <= 8) {
            $noofpages = 1;
        }
        // Loop through each date page for the current set of students
        for ($datePage = 0; $datePage <= $noofpages; $datePage++) {
            if ($datePage == 0) {
                $dateStart = 0;
            } elseif ($datePage == 1) {
                if ($totalDates > 4 && $totalDates <= 8) {
                    $dateStart = $totalDates - 1;
                } else {
                    $dateStart = $datePage * 8;
                    $datesPerPage = 12;
                }
            } else {
                $dateStart = 8 + (($datePage - 1) * $datesPerPage);
            }

            $dateEnd = min($dateStart + $datesPerPage, $totalDates);
            if ($totalDates > 4 && $totalDates <= 8) {
                $dateEnd = $totalDates - 1;
                if ($datePage == 1) {
                    $dateEnd = $totalDates;
                }
                $fs = 9.5;
            } else {
                $fs = 9.5;
            }

            $mpdf->AddPage();
            // If it's the first date page, include S.No., Adm.No., and Name columns
            $html = '
            <table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;">
                <thead>
                    <tr>';

            if ($datePage == 0) {
                $html .= '
                        <th rowspan="2" style="width:30px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">S.No</th>
                        <th rowspan="2" style="width:60px; text-align:left; vertical-align:middle; font-size:' . $fs . 'px;">Adm.No</th>
                        <th rowspan="2" style="width:320px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Name.</th>';
            } else {
                $html .= '
                        <th rowspan="2" style="width:60px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Adm.No</th>';
            }

            // Date headers with serial numbers
            for ($i = $dateStart; $i < $dateEnd; $i++) {
                $classNo = $i + 1;
                list($year, $month, $day, $hour_id, $hour) = explode('-', $dateHours[$i]);
                $formattedDate = "$day/$month ($hour)";
                $html .= '<th style="width:30px; text-align:center; font-size:' . $fs . 'px;">' . $classNo . '</th>';
            }

            if ($datePage == $noofpages) {
                $html .= '<th rowspan=2 style="width:50px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Total Held</th>';
                $html .= '<th rowspan=2 style="width:50px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Total Attended</th>';
                $html .= '<th rowspan=2 style="width:50px; text-align:center; vertical-align:middle; font-size:' . $fs . 'px;">Percentage</th>';
            }

            $html .= '</tr>
                    <tr>';

            // Date row
            for ($i = $dateStart; $i < $dateEnd; $i++) {
                list($year, $month, $day, $hour) = explode('-', $dateHours[$i]);
                $formattedDate = "$day/$month";
                $html .= '<th style="width:30px; text-align:center; font-size:' . $fs . 'px;">' . $formattedDate . '</th>';
            }

            $html .= '</tr>
                </thead>
                <tbody>';

            // Loop through the students for this page
            for ($j = $studentStart; $j < $studentEnd; $j++) {
                $student = $attendanceData['data'][$j];
                $row = '<tr>';

                if ($datePage == 0) {
                    if ($totalDates > 4 && $totalDates <= 8) {
                        $fs = 9.5;
                    } else {
                        $fs = 9.5;
                    }
                    $row .= '
                            <td style="text-align:center; font-size:' . $fs . 'px;">' . $sno++ . '</td>
                            <td style="text-align:center; font-size:' . $fs . 'px;">' . $student['username'] . '</td>
                            <td style="text-align:left; font-size:' . $fs . 'px;">' . $student['name'] . '</td>';
                } else {
                    if ($totalDates == (($datePage * 12) + 8)) {
                        $fs = 9.5;
                    } else {
                        $fs = 9.4;
                    }
                    $row .= '
                            <td style="text-align:center; font-size:' . $fs . 'px;">' . $student['username'] . '</td>';
                }

                for ($i = $dateStart; $i < $dateEnd; $i++) {
                    $dateHour = $dateHours[$i];
                    $status = $student['attendance'][$dateHour] ?? 'N/A';
                    $row .= '<td style="width:30px; font-size:9.4px; text-align:center;">' . $status . '</td>';
                }

                // Add totals only on the last date page
                if ($datePage == $noofpages) {
                    $row .= '<td style="text-align:center; font-size:9.4px;">' . $studentsAttendance[$student['username']]['total_held'] . '</td>';
                    $row .= '<td style="text-align:center; font-size:9.4px;">' . $studentsAttendance[$student['username']]['total_attended'] . '</td>';
                    $row .= '<td style="text-align:center; font-size:9.4px;">' . $studentsAttendance[$student['username']]['percentage'] . '%</td>';
                }

                $row .= '</tr>';

                $html .= $row;
            }

            $html .= '</tbody></table>';
            $mpdf->WriteHTML($html);

            // Add a page break if necessary
            if ($datePage < $noofpages - 1 || $studentPage < $totalStudentPages - 1) {
                //$mpdf->AddPage();
            }
        }
    }


    // Add the Diary of Lecturer Classes section
    $diaryEntries = $facultyObj->getDiaryEntriesBySubject($sub_id, $start_date, $end_date);

    if (!empty($diaryEntries)) {
        $mpdf->AddPage();
        $diaryHeader = '
    <h3 style="text-align:center;">DIARY OF LECTURER CLASSES</h3>
    <table border="1" cellpadding="10" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;">
        <thead>
            <tr>
                <th style="width:30px; text-align:center; vertical-align:middle;">S.No</th>
                <th style="width:90px; text-align:center; vertical-align:middle;">Date</th>
                <th style="width:100px; text-align:center; vertical-align:middle;">Time</th>
                <th style="text-align:center; vertical-align:middle;">Topics Covered</th>
            </tr>
        </thead>
        <tbody>';

        // Loop through the diary entries
        $diaryContent = '';
        $sno = 1;
        foreach ($diaryEntries as $entry) {
            $diaryContent .= '
            <tr>
                <td style="text-align:center;">' . $sno++ . '</td>
                <td style="text-align:center;">' . date('d-m-Y', strtotime($entry['date'])) . '</td>
                <td style="text-align:center;"> ' . date("g:i A", strtotime($entry['start_time'])) . " - " . date("g:i A", strtotime($entry['end_time'])) . '</td>
                <td style="text-align:left;">' . $entry['diary'] . '</td>
            </tr>';
        }

        $diaryFooter = '</tbody></table>';

        // Combine the header, content, and footer
        $diaryHTML = $diaryHeader . $diaryContent . $diaryFooter;

        // Add the diary section to the PDF
        $mpdf->WriteHTML($diaryHTML);
    }


    // Check if both assessments are present
    $studentList = $facultyObj->getMappedStudents($sub_id);
    $assessmentDetails = [];
    // Check if both assessments are present
    $selected_sub_id = $sub_id;
    require_once("modulecheckcia.php");

    if ($includeInternalMarks) {
        $mpdf->AddPage(); // Add a new page for internal marks

        $internalMarksHTML = '';

        if (!empty($prg_res['prg_code'])) {
            if ($prg_res['prg_code'] == "A") {

                if ($prg_res["sub_type"] == "project") {
                    $internalMarksHeader = '
                <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;">
                    <thead>
                        <tr>
                            <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th style="width:60px; font-size:11; text-align:center; vertical-align:middle;">Supervisor</th>
                            <th style="width:60px; font-size:11; text-align:center; vertical-align:middle;">P. R. C.</th>
                            <th style="width:60px; font-size:11; text-align:center; vertical-align:middle;">CIA (Max 60)</th>
                        </tr>
                    </thead>
                    <tbody>';
                    $internalMarksFooter = '</tbody></table>';
                    $internalMarksContent = '';
                    $sno = 1;
                    foreach ($studentList as $student) {
                        $c1 = rtrim(rtrim(number_format($student['marks'][1]['component1_marks'], 1), '0'), '.');
                        $c2 = rtrim(rtrim(number_format($student['marks'][1]['component2_marks'], 1), '0'), '.');
                        if (empty($c1)) $c1 = 0;
                        if (empty($c2)) $c2 = 0;
                        $total = ($student['marks'][1]['component1_marks'] !== null) ? round($student['marks'][1]['component1_marks'] + $student['marks'][1]['component2_marks']) : '';
                        $internalMarksContent .= '<tr>
                    <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
                    <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>
                    <td style="font-size:11; text-align:center;">' . $c1 . '</td>
                    <td style="font-size:11; text-align:center;">' . $c2 . '</td>
                    <td style="font-size:11; text-align:center;">' . $total . '</td>
                </tr>';
                    }
                    $internalMarksHTML = $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                } elseif ($prg_res["sub_type"] == "lab" || $prg_res["sub_type"] == "dti") {
                    if ($prg_res["sub_type"] == "dti") {
                        $col1 = "Activity";
                    } else {
                        $col1 = "Day-to-Day";
                    }
                    $col2 = "Internal";
                    // Internal marks table header
                    $internalMarksHeader = '
                <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th colspan="3" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment</th>
                        </tr>
                        <tr>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">'.$col1.'</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">'.$col2.'</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA</th>
                        </tr>
                    </thead>
                    <tbody>';

                    $internalMarksFooter = '</tbody>
                </table>';

                    // Loop through the students and add their internal marks to the table
                    $internalMarksContent = '';
                    $sno = 1;

                    foreach ($studentList as $student) {

                        $subj1 =  rtrim(rtrim(number_format($student['marks'][1]['day_to_day_marks'], 1), '0'), '.');
                        $obj1 =  rtrim(rtrim(number_format($student['marks'][1]['internal_test_marks'], 1), '0'), '.');

                        if (empty($subj1)) {
                            $subj1 = 0;
                        }
                        if (empty($obj1)) {
                            $obj1 = 0;
                        }

                        $assessment1Total = $student['marks'][1]['day_to_day_marks'] + $student['marks'][1]['internal_test_marks'];
                        $assessment1Total = round($assessment1Total);
                        $internalMarksContent .= '
                <tr>
                    <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
                    <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>
                    <td style="font-size:11; text-align:center;">' . $subj1 . '</td>
                    <td style="font-size:11; text-align:center;">' . $obj1 . '</td>
                    <td style="font-size:11; text-align:center;">' . $assessment1Total . '</td>';

                        $internalMarksContent .= '
                </tr>';
                    }
                } else {
                    // Internal marks table header
                    $internalMarksHeader = '
                <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                    <thead>
                        <tr>
                            <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 1</th>
                            <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 2</th>
                            <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $betterWeightPct . '% of Best</th>
                            <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $lesserWeightPct . '% of Rest</th>
                            <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                        </tr>
                        <tr>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Sub-1</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Obj-1</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Ass-1</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA-1</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Sub-2</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Obj-2</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Ass-2</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">CIA-2</th>
                        </tr>
                    </thead>
                    <tbody>';

                    $internalMarksFooter = '</tbody>
                    <tfoot>
                        <tr>
                            <td colspan="11" style="font-size:9px; color:#444; background:#f9f9f9; padding:4px 8px;">
                                <strong>Regulatory Standards (' . htmlspecialchars($regulation) . '):</strong>
                                [CIA Weightage: ' . $betterWeightPct . '% Better Mid + ' . $lesserWeightPct . '% Lower Mid] &bull;
                                [PO Attainment: ' . $overallDirectPct . '% Direct + ' . $overallIndirectPct . '% Indirect] &bull;
                                [CO Attainment: ' . $directCiaPct . '% CIA + ' . $directSeePct . '% SEE]
                            </td>
                        </tr>
                    </tfoot>
                </table>';

                    // Loop through the students and add their internal marks to the table
                    $internalMarksContent = '';
                    $sno = 1;

                    foreach ($studentList as $student) {

                        $subj1 =  rtrim(rtrim(number_format($student['marks'][1]['subjective_marks'], 1), '0'), '.');
                        $obj1 =  rtrim(rtrim(number_format($student['marks'][1]['objective_marks'], 1), '0'), '.');
                        $assgn1 =  rtrim(rtrim(number_format($student['marks'][1]['assignment_marks'], 1), '0'), '.');

                        if (empty($subj1)) {
                            $subj1 = 0;
                        }
                        if (empty($obj1)) {
                            $obj1 = 0;
                        }
                        if (empty($assgn1)) {
                            $assgn1 = 0;
                        }

                        $assessment1Total = $student['marks'][1]['subjective_marks'] + $student['marks'][1]['objective_marks'] + $student['marks'][1]['assignment_marks'];
                        $internalMarksContent .= '
                <tr>
                    <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
                    <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>
                    <td style="font-size:11; text-align:center;">' . $subj1 . '</td>
                    <td style="font-size:11; text-align:center;">' . $obj1 . '</td>
                    <td style="font-size:11; text-align:center;">' . $assgn1 . '</td>
                    <td style="font-size:11; text-align:center;">' . $assessment1Total . '</td>';

                        if ($student['marks'][2]['subjective_marks'] != null) {

                            $assessment2Total = $student['marks'][2]['subjective_marks'] + $student['marks'][2]['objective_marks'] + $student['marks'][2]['assignment_marks'];

                            // Dynamic weighting derived from centralized academic settings
                            if ($assessment1Total > $assessment2Total) {
                                $best = $assessment1Total;
                                $rest = $assessment2Total;
                            } else {
                                $best = $assessment2Total;
                                $rest = $assessment1Total;
                            }

                            $eightyPercent = round($midBetterWeight * $best, 2);
                            $twentyPercent = round($midLesserWeight * $rest, 2);
                            $totalCIA = round($eightyPercent + $twentyPercent, 2);

                            $subj2 =  rtrim(rtrim(number_format($student['marks'][2]['subjective_marks'], 1), '0'), '.');
                            $obj2 =  rtrim(rtrim(number_format($student['marks'][2]['objective_marks'], 1), '0'), '.');
                            $assgn2 =  rtrim(rtrim(number_format($student['marks'][2]['assignment_marks'], 1), '0'), '.');

                            if (empty($subj2)) {
                                $subj2 = 0;
                            }
                            if (empty($obj2)) {
                                $obj2 = 0;
                            }
                            if (empty($assgn2)) {
                                $assgn2 = 0;
                            }


                            $internalMarksContent .= '
                    <td style="font-size:11; text-align:center;">' . $subj2 . '</td>
                    <td style="font-size:11; text-align:center;">' . $obj2 . '</td>
                    <td style="font-size:11; text-align:center;">' . $assgn2 . '</td>
                    <td style="font-size:11; text-align:center;">' . $assessment2Total . '</td>
                    <td style="font-size:11; text-align:center;">' . round($eightyPercent, 2) . '</td>
                    <td style="font-size:11; text-align:center;">' . round($twentyPercent, 2) . '</td>
                    <td style="font-size:11; text-align:center;">' . round($totalCIA) . '</td>';
                        } else {
                            $internalMarksContent .= '
                    <td style="font-size:11; text-align:center;"></td>
                    <td style="font-size:11; text-align:center;"></td>
                    <td style="font-size:11; text-align:center;"></td>
                    <td style="font-size:11; text-align:center;"></td>
                    <td style="font-size:11; text-align:center;"></td>
                    <td style="font-size:11; text-align:center;"></td>
                    <td style="font-size:11; text-align:center;"></td>
                    ';
                        }
                        $internalMarksContent .= '
                </tr>';
                    }
                }
                $internalMarksHTML = $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
            } else {
                if ($prg_res["sub_type"] == "lab") {
                    // Internal marks table header PG
                    $internalMarksHeader = '
                <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                        </tr>
                    </thead>
                    <tbody>';

                    $internalMarksFooter = '</tbody>
                </table>';

                    // Loop through the students and add their internal marks to the table
                    $internalMarksContent = '';
                    $sno = 1;

                    foreach ($studentList as $student) {

                        $stmarks1 =  rtrim(rtrim(number_format($student['marks'][11]['marks'], 1), '0'), '.');

                        if (empty($stmarks1)) {
                            $stmarks1 = 0;
                        }

                        $internalMarksContent .= '
                <tr>
                    <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
                    <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>';

                        if ($student['marks'][11]['marks'] != null) {
                            $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks1 . '</td>';
                        } else {
                            $internalMarksContent .= '<td></td>';
                        }

                        $internalMarksContent .= '
                </tr>';
                    }
                    $internalMarksHTML = $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                } else {
                    // Internal marks table header PG
                    $internalMarksHeader = '
                <table border="1" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                    <thead>
                        <tr>
                            <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                            <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                            <th style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Internal Assessment - 1</th>
                            <th style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Internal Assessment - 2</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">70% of Best</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">30% of Rest</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final Internal</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Assignment</th>
                            <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">Final CIA</th>
                        </tr>
                    </thead>
                    <tbody>';

                    $internalMarksFooter = '</tbody>
                </table>';

                    // Loop through the students and add their internal marks to the table
                    $internalMarksContent = '';
                    $sno = 1;

                    foreach ($studentList as $student) {

                        $stmarks1 =  rtrim(rtrim(number_format($student['marks'][1]['marks'], 1), '0'), '.');
                        $stmarks2 =  rtrim(rtrim(number_format($student['marks'][2]['marks'], 1), '0'), '.');
                        $stmarks3 =  rtrim(rtrim(number_format($student['marks'][3]['marks'], 1), '0'), '.');

                        if (empty($stmarks1)) {
                            $stmarks1 = 0;
                        }
                        if (empty($stmarks2)) {
                            $stmarks2 = 0;
                        }
                        if (empty($stmarks3)) {
                            $stmarks3 = 0;
                        }

                        $internalMarksContent .= '
                <tr>
                    <td style="font-size:11; text-align:center;">' . $sno++ . '</td>
                    <td style="font-size:11; text-align:center;">' . $student['username'] . '</td>';

                        if ($student['marks'][1]['marks'] != null) {
                            $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks1 . '</td>';
                        } else {
                            $internalMarksContent .= '<td></td>';
                        }
                        if ($student['marks'][2]['marks'] != null) {
                            $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks2 . '</td>';
                        } else {
                            $internalMarksContent .= '<td></td>';
                        }
                        if ($student['marks'][1]['marks'] != null && $student['marks'][2]['marks'] != null) {
                            if ($stmarks1 > $stmarks2) {
                                $best = $stmarks1;
                                $rest = $stmarks2;
                            } else {
                                $best = $stmarks2;
                                $rest = $stmarks1;
                            }

                            $seventyPercent = 0.7 * $best;
                            $thirtyPercent = 0.3 * $rest;
                            $totalInt = $seventyPercent + $thirtyPercent;
                            $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $seventyPercent . '</td><td style="font-size:11; text-align:center;">' . $thirtyPercent . '</td><td style="font-size:11; text-align:center;">' . $totalInt . '</td>';
                        } else {
                            $internalMarksContent .= '<td></td><td></td><td></td>';
                        }

                        if ($student['marks'][3]['marks'] != null) {
                            $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $stmarks3 . '</td>';
                        } else {
                            $internalMarksContent .= '<td></td>';
                        }

                        if (isset($totalInt) && $student['marks'][3]['marks'] != null) {
                            $totalCIA = $totalInt + $stmarks3;
                            $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $totalCIA . '</td>';
                        } else {
                            $internalMarksContent .= '<td></td>';
                        }
                        $internalMarksContent .= '
                </tr>';
                    }
                    $internalMarksHTML = $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                }
            }
        }
        // Add the internal marks section to the PDF
        $mpdf->WriteHTML($internalMarksHTML);
    }

    // Output PDF to browser
    $mpdf->Output('attendance_report_' . date("dmyhis") . '.pdf', 'D');
} else {
    echo "No attendance data found for this subject.";
}
