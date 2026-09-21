<?php if (!empty($_POST['action'])) : 
function e($str) {
    return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                <?php if (!empty($details['data'])) : ?>
                    <strong>Class:</strong> <?php echo $details['data']['classname'] . '(' . $details['data']['acad_year'] . ')'; ?><br />
                    <strong>Subject:</strong> <?php echo $details['data']['sub_fullname']; ?><br />
                    <strong>Faculty:</strong> <?php echo $details['data']['faculty_name']; ?><br />
                    <?php
                    if (!empty($start_date) && !empty($end_date)) {
                        list($year1, $month1, $day1) = explode('-', $start_date);
                        list($year2, $month2, $day2) = explode('-', $end_date);
                        $daterange = $day1 . '/' . $month1 . '/' . $year1 . ' - ' . $day2 . '/' . $month2 . '/' . $year2;
                        echo '<strong>Date Range:</strong> ' . $daterange . '';
                    }
                    ?>
                <?php endif; ?>
            </div>

            <?php
            if (!empty($_POST['action']) && $_POST['action'] == "show_cia" && !empty($_POST['cls_id'])) {
                echo '<div class="float-end"><a href="generate_class_report.php?class_id=' . $_POST['cls_id'] . '" target="_blank" class="float-end btn btn-outline-success">Download Overall Class CIA</a></div>';
            }
            ?>

        </div>
        <div class="card-body table-responsive">
            <?php
            // View Overall Attendance Section
            if (!empty($attendanceData) && $_POST['action'] == 'view_overall') : ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Adm. No.</th>
                            <th>Student Name</th>
                            <th>Overall Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($attendanceData['data'] as $student) {
                            echo "<tr>";
                            echo "<td>" . e($student['username']) . "</td>";
                            echo "<td>" . e($student['name']) . "</td>";
                            echo "<td>{$student['percentage']}%</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <?php

                $lessThan40 = [];
                $lessThan65 = [];
                $between65And75 = [];

                // Process attendance data
                foreach ($attendanceData['data'] as $student) {
                    $percentage = $student['percentage'];
                    $rollNumber = $student['username']; // Assuming roll number is stored in 'username'

                    if ($percentage < 65) {
                        $lessThan65[] = $rollNumber;
                        if ($percentage < 40) {
                            $lessThan40[] = $rollNumber;
                        }
                    } elseif ($percentage >= 65 && $percentage < 75) {
                        $between65And75[] = $rollNumber;
                    }
                }

                // Function to format roll numbers as a comma-separated string
                function formatRollNumbers($rollNumbers)
                {
                    return implode(', ', $rollNumbers);
                }
                ?>

                <!-- Display the statistics in a table format -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th></th>
                            <th>No. of Students</th>
                            <th>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-primary">65% - 75%</td>
                            <td class="text-primary"><?php echo count($between65And75); ?></td>
                            <td class="text-primary"><?php echo formatRollNumbers($between65And75); ?></td>
                        </tr>
                        <tr>
                            <td class="text-danger">
                                < 65%</td>
                            <td class="text-danger"><?php echo count($lessThan65); ?></td>
                            <td class="text-danger"><?php echo formatRollNumbers($lessThan65); ?></td>
                        </tr>
                        <tr>
                            <td class="text-secondary">
                                < 40%</td>
                            <td class="text-secondary"><?php echo count($lessThan40); ?></td>
                            <td class="text-secondary"><?php echo formatRollNumbers($lessThan40); ?></td>
                        </tr>
                    </tbody>
                </table>


            <?php
            // View Detailed Attendance Section
            elseif (!empty($attendanceData) && $_POST['action'] == 'view_detailed' && !empty($attendanceData['data'][0]['attendance'])) :
                // Get unique date-hour combinations
                $dateHours = array_keys($attendanceData['data'][0]['attendance']);
            ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Adm. No.</th>
                            <th>Student Name</th>
                            <?php foreach ($dateHours as $dateHour) : ?>
                                <th><?php
                                    list($year, $month, $day, $hour_id, $hour) = explode('-', $dateHour);
                                    $formattedDate = "$day/$month<br>$hour";
                                    echo $formattedDate;
                                    ?></th>
                            <?php endforeach; ?>
                            <th>Total Classes Held</th>
                            <th>Classes Present</th>
                            <th>Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($attendanceData['data'] as $student) {
                            echo "<tr>";
                            echo "<td>" . e($student['username']) . "</td>";
                            echo "<td>" . e($student['name']) . "</td>";
                            foreach ($dateHours as $dateHour) {
                                $status = $student['attendance'][$dateHour];
                                echo "<td>{$status}</td>";
                            }
                            echo "<td>{$student['total_classes']}</td>"; // Total classes held
                            echo "<td>{$student['total_present']}</td>";
                            echo "<td>{$student['percentage']}%</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <br>
                <form action="download_attendance.php" method="post">
                    <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                    <input type="hidden" name="start_date" value="<?php if (!empty($start_date)) {
                                                                        echo $start_date;
                                                                    } ?>">
                    <input type="hidden" name="end_date" value="<?php if (!empty($end_date)) {
                                                                    echo $end_date;
                                                                } ?>">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <button type="submit" class="btn btn-success">Download Detailed Attendance</button>
                </form>
                <br>
                <form action="download_bluebook.php" method="post">
                    <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>">
                    <input type="hidden" name="start_date" value="<?php if (!empty($start_date)) {
                                                                        echo $start_date;
                                                                    } ?>">
                    <input type="hidden" name="end_date" value="<?php if (!empty($end_date)) {
                                                                    echo $end_date;
                                                                } ?>">
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    <button type="submit" class="btn btn-primary">Download e-Bluebook PDF</button>
                </form>
            <?php elseif (!empty($selected_sub_id) && empty($attendanceData['data']) && empty($diaryEntries) && empty($includeInternalMarks)) :
                if (!empty($_POST['action']) && $_POST['action'] == "show_cia" && empty($includeInternalMarks)) {
                    echo '<div class="alert alert-info">No Continuous Internal Assessment found for this subject.</div>';
                } elseif (!empty($_POST['action']) && $_POST['action'] == "show_diary" && empty($diaryEntries)) {
                    echo '<div class="alert alert-info">No Diary data found for this subject.</div>';
                } else {
                    echo '<div class="alert alert-info">No attendance data found for this subject.</div>';
                }
            ?>
            <?php endif; ?>


            <?php if (!empty($diaryEntries)) : ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Diary</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($diaryEntries as $entry) {
                            echo "<tr>";
                            echo "<td>" . date('d-m-Y', strtotime($entry['date'])) . "</td>";
                            echo "<td>" . date("g:i A", strtotime($entry['start_time'])) . " - " . date("g:i A", strtotime($entry['end_time'])) . "</td>";
                            echo "<td>" . e($entry['diary']) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <?php
            if (!empty($includeInternalMarks)) {

                if (!empty($prg_res['prg_code'])) {
                    if ($prg_res['prg_code'] == "A") {
                        if ($prg_res["sub_type"] == "project") {
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
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
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        } elseif ($prg_res["sub_type"] == "lab" || $prg_res["sub_type"] == "dti") {
                            if ($prg_res["sub_type"] == "dti") {
                                $col1 = "Activity";
                            } else {
                                $col1 = "Day-to-Day";
                            }
                            $col2 = "Internal";
                            // Internal marks table header
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                                    <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                                    <th colspan="3" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment</th>
                                </tr>
                                <tr>
                                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $col1 . '</th>
                                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">' . $col2 . '</th>
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
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        } else {
                            // Internal marks table header
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th rowspan="2" style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                                    <th rowspan="2" style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                                    <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 1</th>
                                    <th colspan="4" style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Continuous Internal Assessment - 2</th>
                                    <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">80% of Best</th>
                                    <th rowspan="2" style="width:50px; font-size:11; text-align:center; vertical-align:middle;">20% of Rest</th>
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

                                    // Consider 80% of the best assessment and 20% of the remaining one
                                    if ($assessment1Total > $assessment2Total) {
                                        $best = $assessment1Total;
                                        $rest = $assessment2Total;
                                    } else {
                                        $best = $assessment2Total;
                                        $rest = $assessment1Total;
                                    }

                                    $eightyPercent = 0.8 * $best;
                                    $twentyPercent = 0.2 * $rest;
                                    $totalCIA = $eightyPercent + $twentyPercent;

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
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        }
                    } elseif ($prg_res['prg_code'] == "D") {
                        
                        if ($prg_res["sub_type"] == "lab") {
                            // Internal marks table header PG
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
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
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        } else {
                            // Internal marks table header PG
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th style="width:25px; font-size:11; text-align:center; vertical-align:middle;">S.No</th>
                                    <th style="width:80px; font-size:11; text-align:center; vertical-align:middle;">Adm.No</th>
                                    <th style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Internal Assessment - 1</th>
                                    <th style="width:200px; font-size:11; text-align:center; vertical-align:middle;">Internal Assessment - 2</th>
                                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">80% of Best</th>
                                    <th style="width:50px; font-size:11; text-align:center; vertical-align:middle;">20% of Rest</th>
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

                                    $seventyPercent = 0.8 * $best;
                                    $thirtyPercent = 0.2 * $rest;
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
                                    $totalCIA = round($totalCIA);
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $totalCIA . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }
                                $internalMarksContent .= '
                        </tr>';
                            }
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        }
                    }else {
                        
                        if ($prg_res["sub_type"] == "lab") {
                            // Internal marks table header PG
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
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
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        } else {
                            // Internal marks table header PG
                            $internalMarksHeader = '
                        <table border="1" id="internalMarksTable" cellpadding="5" cellspacing="0" style="font-size: 12px; border-collapse:collapse; width: 100%;" class="table table-bordered">
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
                                    $totalCIA = round($totalCIA);
                                    $internalMarksContent .= '<td style="font-size:11; text-align:center;">' . $totalCIA . '</td>';
                                } else {
                                    $internalMarksContent .= '<td></td>';
                                }
                                $internalMarksContent .= '
                        </tr>';
                            }
                            echo $internalMarksHeader . $internalMarksContent . $internalMarksFooter;
                        }
                    }
                }
            ?>
                <form id="exportForm" method="post" action="export_excel.php">
                    <input type="hidden" name="table_html" id="table_html" />
                    <input type="hidden" name="class" value="<?php echo $details['data']['classname'] . '(' . $details['data']['acad_year'] . ')'; ?>" />
                    <input type="hidden" name="subject" value="<?php echo $details['data']['sub_fullname']; ?>" />
                    <input type="hidden" name="faculty" value="<?php echo $details['data']['faculty_name']; ?>" />
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>" />

                    <button type="submit" class="btn btn-success">Download as Excel</button>
                </form>

                <script>
                    document.getElementById("exportForm").addEventListener("submit", function(e) {
                        var tableHtml = document.querySelector("#internalMarksTable").outerHTML; // use your actual table ID
                        document.getElementById("table_html").value = tableHtml;
                    });
                </script>
            <?php
            }
            if (!empty($_POST['action']) && $_POST['action'] == "show_cia" && !empty($facultyObj)) {
                $attachments = $facultyObj->getCIAAttachmentsBySubId($selected_sub_id);
                if (!empty($attachments['count']) && $attachments['count'] > 0) {
                    echo '<br><table class="table table-striped">';
                    echo '<tr><td>S.No.</td><td>Assessment No.</td><td>Title</td><td>Download</td></tr>';
                    $sno = 1;
                    foreach ($attachments["files"] as $attachment) {
                        echo '<tr><td>' . $sno . '</td><td>' . $attachment['assessment_number'] . '</td><td>' . $attachment['file_title'] . '</td><td><a href="' . $attachment['file_path'] . '" target="_blank" download class="btn btn-sm btn-primary">Download</a></td></tr>';
                        $sno++;
                    }
                    echo '</table>';
                }
            }
            ?>
        </div>
    </div>
<?php endif; ?>