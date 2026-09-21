<?php if (!empty($_POST['action'])) : 
function e($str) {
return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8');
}
?>
    <div class="card">
        <div class="card-header">
            <?php 
            if (!empty($details['data'])) : 
                $cls_arr = explode(" ", $details['data']['classname']);  
                ?>
                <strong>Class:</strong> <?php echo $details['data']['classname'] . '(' . $details['data']['acad_year'] . ')'; ?>
            <?php endif; ?>
        </div>
        <div class="card-body table-responsive">
            <?php
            // View Overall Attendance Section
            if (!empty($attendanceData) && $_POST['action'] == 'view_all_overall' && $cls_arr[0]=="B.Tech") : ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Adm. No.</th>
                            <th>Name of the Student</th>
                            <?php foreach ($sub_sno_array as $sno) : ?>
                                <th style="text-align: center;"><?php echo $sno; ?></th>
                            <?php endforeach; ?>
                            <th style="text-align: center;">Overall %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $lessThan40BySubject = []; // To hold <40% students per subject
                        $lessThan65 = [];
                        $between65And75 = [];

                        foreach ($studentList as $student) {
                            $rollNumber = $student['roll_number'];
                            $studentName = $student['name'];
                            $totalPercentage = 0;
                            $subjectCount = 0;

                            echo "<tr>";
                            echo "<td>" . e($rollNumber) . "</td>";
                            echo "<td>" . e($studentName) . "</td>";

                            $lessThan40ByStudent = 0;
                            foreach ($sub_sno_array as $sno) {
                                if (isset($attendanceData[$rollNumber][$sno])) {
                                    $attendance = $attendanceData[$rollNumber][$sno];
                                    echo "<td style='text-align: center;'>{$attendance}%</td>";
                                    $totalPercentage += $attendance;
                                    $subjectCount++;

                                    // Check if attendance is below 40% for this subject
                                    if ($attendance < 40) {
                                        $lessThan40BySubject[$sno][] = $rollNumber;
                                        $lessThan40ByStudent = 1;
                                    }
                                } else {
                                    echo "<td style='text-align: center;'>-</td>";
                                }
                            }

                            // Calculate overall attendance percentage
                            $overallPercentage = ($subjectCount > 0) ? round($totalPercentage / $subjectCount, 2) : 0;
                            echo "<td style='text-align: center;'>{$overallPercentage}%</td>";
                            echo "</tr>";

                            // Categorize students based on overall percentage
                            if ($overallPercentage < 65) {
                                $lessThan65[] = $rollNumber;
                            }
                            if ($overallPercentage >= 65 && $overallPercentage < 75 && $lessThan40ByStudent == 0) {
                                $between65And75[] = $rollNumber;
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <?php
                $mappingList = $hodObj->getFacSubMapByClassID($selected_cls_id);
                ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject - Faculty</th>
                        </tr>
                        <tr>
                            <th style="width: 30px; text-align: center; vertical-align:middle;">S.No</th>
                            <th>Subject (Code - Name)</th>
                            <th>Name of the Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($mappingList['status']) && $mappingList['status'] == 1) {
                            // Step 1: Group data by subject_sno and then by subject within each SNO
                            $groupedData = [];
                            foreach ($mappingList['data'] as $mapping) {
                                $sno = $mapping['subject_sno'];
                                $subjectCode = $mapping['subcode'];
                                $subjectName = "{$mapping['subcode']} - {$mapping['sub_fullname']}";
                                $facultyName = $mapping['faculty_name'];

                                // Initialize group for each SNO
                                if (!isset($groupedData[$sno])) {
                                    $groupedData[$sno] = [];
                                }

                                // Group by subject within each SNO
                                if (!isset($groupedData[$sno][$subjectCode])) {
                                    $groupedData[$sno][$subjectCode] = [
                                        'subject_name' => $subjectName,
                                        'faculties' => []
                                    ];
                                }

                                // Add faculty to the subject within the SNO
                                $groupedData[$sno][$subjectCode]['faculties'][] = $facultyName;
                            }

                            // Step 2: Display the data in the table
                            foreach ($groupedData as $sno => $subjects) {
                                $snoRowspan = count($subjects); // Number of subjects for the SNO

                                $isFirstSubject = true; // Track if it's the first subject for rowspan
                                foreach ($subjects as $subjectDetails) {
                                    $subjectName = $subjectDetails['subject_name'];
                                    $facultyList = implode(', ', $subjectDetails['faculties']); // Join faculties with comma

                                    echo "<tr>";
                                    // Display SNO with rowspan only on the first subject row
                                    if ($isFirstSubject) {
                                        echo "<td rowspan='{$snoRowspan}' style='width: 30px; text-align: center; vertical-align:middle;'>{$sno}</td>";
                                        $isFirstSubject = false;
                                    }
                                    // Display subject and faculties
                                    echo "<td>" . e($subjectName) . "</td>";
                                    echo "<td>" . e($facultyList) . "</td>";
                                    echo "</tr>";
                                }
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <!-- Display Overall Attendance Summary -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Condonation & Detained List Based on Overall Attendance </th>
                        </tr>
                        <tr>
                            <th style='text-align: center;'>Range</th>
                            <th style='text-align: center;'>Count</th>
                            <th style='text-align: center;'>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="text-primary" style='text-align: center;'>65%&nbsp;-&nbsp;75%</td>
                            <td class="text-primary" style='text-align: center;'><?php echo count($between65And75); ?></td>
                            <td class="text-primary"><?php echo implode(', ', $between65And75); ?></td>
                        </tr>
                        <tr>
                            <td class="text-danger" style='text-align: center;'>
                                < 65%</td>
                            <td class="text-danger" style='text-align: center;'><?php echo count($lessThan65); ?></td>
                            <td class="text-danger"><?php echo implode(', ', $lessThan65); ?></td>
                        </tr>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <!-- Display Subject-wise Attendance Below 40% -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject-wise Attendance Below 40%</th>
                        </tr>
                        <tr>
                            <th style='text-align: center;'>S.No.</th>
                            <th style='text-align: center;'>Count</th>
                            <th>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        ksort($lessThan40BySubject);
                        foreach ($lessThan40BySubject as $subjectCode => $students) {
                            echo "<tr>";
                            echo "<td style='text-align: center;'>{$subjectCode}</td>";
                            echo "<td style='text-align: center;'>" . count($students) . "</td>";
                            echo "<td>" . implode(', ', $students) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            <?php elseif($cls_arr[0]=="M.Tech"): ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Adm. No.</th>
                            <th>Name of the Student</th>
                            <?php foreach ($sub_sno_array as $sno) : ?>
                                <th style="text-align: center;"><?php echo $sno; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $lessThan40BySubject = []; // To hold <40% students per subject
                        $lessThan50BySubject = [];
                        $lessThan65BySubject = [];
                        $between65And75BySubject = [];

                        foreach ($studentList as $student) {
                            $rollNumber = $student['roll_number'];
                            $studentName = $student['name'];
                            $subjectCount = 0;

                            echo "<tr>";
                            echo "<td>" . e($rollNumber) . "</td>";
                            echo "<td>" . e($studentName) . "</td>";

                            foreach ($sub_sno_array as $sno) {
                                if (isset($attendanceData[$rollNumber][$sno])) {
                                    $attendance = $attendanceData[$rollNumber][$sno];
                                    echo "<td style='text-align: center;'>{$attendance}%</td>";
                                    $subjectCount++;

                                    // Check if attendance is below 40% for this subject

                                    if ($attendance < 40) {
                                        $lessThan40BySubject[$sno][] = $rollNumber;
                                    }
                                    if ($attendance < 50) {
                                        $lessThan50BySubject[$sno][] = $rollNumber;
                                    }
                                    if ($attendance < 65) {
                                        $lessThan65BySubject[$sno][] = $rollNumber;
                                    }

                                    if ($attendance >= 65 && $attendance < 75) {
                                        $between65And75BySubject[$sno][] = $rollNumber;
                                    }
                                } else {
                                    echo "<td style='text-align: center;'>-</td>";
                                }
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <?php
                $mappingList = $hodObj->getFacSubMapByClassID($selected_cls_id);
                ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject - Faculty</th>
                        </tr>
                        <tr>
                            <th style="width: 30px; text-align: center; vertical-align:middle;">S.No</th>
                            <th>Subject (Code - Name)</th>
                            <th>Name of the Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($mappingList['status']) && $mappingList['status'] == 1) {
                            // Step 1: Group data by subject_sno and then by subject within each SNO
                            $groupedData = [];
                            foreach ($mappingList['data'] as $mapping) {
                                $sno = $mapping['subject_sno'];
                                $subjectCode = $mapping['subcode'];
                                $subjectName = "{$mapping['subcode']} - {$mapping['sub_fullname']}";
                                $facultyName = $mapping['faculty_name'];

                                // Initialize group for each SNO
                                if (!isset($groupedData[$sno])) {
                                    $groupedData[$sno] = [];
                                }

                                // Group by subject within each SNO
                                if (!isset($groupedData[$sno][$subjectCode])) {
                                    $groupedData[$sno][$subjectCode] = [
                                        'subject_name' => $subjectName,
                                        'faculties' => []
                                    ];
                                }

                                // Add faculty to the subject within the SNO
                                $groupedData[$sno][$subjectCode]['faculties'][] = $facultyName;
                            }

                            // Step 2: Display the data in the table
                            foreach ($groupedData as $sno => $subjects) {
                                $snoRowspan = count($subjects); // Number of subjects for the SNO

                                $isFirstSubject = true; // Track if it's the first subject for rowspan
                                foreach ($subjects as $subjectDetails) {
                                    $subjectName = $subjectDetails['subject_name'];
                                    $facultyList = implode(', ', $subjectDetails['faculties']); // Join faculties with comma

                                    echo "<tr>";
                                    // Display SNO with rowspan only on the first subject row
                                    if ($isFirstSubject) {
                                        echo "<td rowspan='{$snoRowspan}' style='width: 30px; text-align: center; vertical-align:middle;'>{$sno}</td>";
                                        $isFirstSubject = false;
                                    }
                                    // Display subject and faculties
                                    echo "<td>" . e($subjectName) . "</td>";
                                    echo "<td>" . e($facultyList) . "</td>";
                                    echo "</tr>";
                                }
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <!-- Display Subject-wise Attendance Below 40% -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject-wise Attendance Above 65% and Less than 75%</th>
                        </tr>
                        <tr>
                            <th style='text-align: center;'>S.No.</th>
                            <th style='text-align: center;'>Count</th>
                            <th>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        ksort($between65And75BySubject);
                        foreach ($between65And75BySubject as $subjectCode => $students) {
                            echo "<tr>";
                            echo "<td style='text-align: center;'>{$subjectCode}</td>";
                            echo "<td style='text-align: center;'>" . count($students) . "</td>";
                            echo "<td>" . implode(', ', $students) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <hr />
                <!-- Display Subject-wise Attendance Below 50% -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject-wise Attendance Below 50%</th>
                        </tr>
                        <tr>
                            <th style='text-align: center;'>S.No.</th>
                            <th style='text-align: center;'>Count</th>
                            <th>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        ksort($lessThan50BySubject);
                        foreach ($lessThan50BySubject as $subjectCode => $students) {
                            echo "<tr>";
                            echo "<td style='text-align: center;'>{$subjectCode}</td>";
                            echo "<td style='text-align: center;'>" . count($students) . "</td>";
                            echo "<td>" . implode(', ', $students) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            
            <?php else: ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Adm. No.<?php echo $cls_arr[0]=="B.Tech"; ?></th>
                            <th>Name of the Student</th>
                            <?php foreach ($sub_sno_array as $sno) : ?>
                                <th style="text-align: center;"><?php echo $sno; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $lessThan40BySubject = []; // To hold <40% students per subject
                        $lessThan50BySubject = [];
                        $lessThan65BySubject = [];
                        $between65And75BySubject = [];

                        foreach ($studentList as $student) {
                            $rollNumber = $student['roll_number'];
                            $studentName = $student['name'];
                            $subjectCount = 0;

                            echo "<tr>";
                            echo "<td>" . e($rollNumber) . "</td>";
                            echo "<td>" . e($studentName) . "</td>";

                            foreach ($sub_sno_array as $sno) {
                                if (isset($attendanceData[$rollNumber][$sno])) {
                                    $attendance = $attendanceData[$rollNumber][$sno];
                                    echo "<td style='text-align: center;'>{$attendance}%</td>";
                                    $subjectCount++;

                                    // Check if attendance is below 40% for this subject

                                    if ($attendance < 40) {
                                        $lessThan40BySubject[$sno][] = $rollNumber;
                                    }
                                    if ($attendance < 50) {
                                        $lessThan50BySubject[$sno][] = $rollNumber;
                                    }
                                    if ($attendance < 65) {
                                        $lessThan65BySubject[$sno][] = $rollNumber;
                                    }

                                    if ($attendance >= 65 && $attendance < 75) {
                                        $between65And75BySubject[$sno][] = $rollNumber;
                                    }
                                } else {
                                    echo "<td style='text-align: center;'>-</td>";
                                }
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <?php
                $mappingList = $hodObj->getFacSubMapByClassID($selected_cls_id);
                ?>
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject - Faculty</th>
                        </tr>
                        <tr>
                            <th style="width: 30px; text-align: center; vertical-align:middle;">S.No</th>
                            <th>Subject (Code - Name)</th>
                            <th>Name of the Faculty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if (!empty($mappingList['status']) && $mappingList['status'] == 1) {
                            // Step 1: Group data by subject_sno and then by subject within each SNO
                            $groupedData = [];
                            foreach ($mappingList['data'] as $mapping) {
                                $sno = $mapping['subject_sno'];
                                $subjectCode = $mapping['subcode'];
                                $subjectName = "{$mapping['subcode']} - {$mapping['sub_fullname']}";
                                $facultyName = $mapping['faculty_name'];

                                // Initialize group for each SNO
                                if (!isset($groupedData[$sno])) {
                                    $groupedData[$sno] = [];
                                }

                                // Group by subject within each SNO
                                if (!isset($groupedData[$sno][$subjectCode])) {
                                    $groupedData[$sno][$subjectCode] = [
                                        'subject_name' => $subjectName,
                                        'faculties' => []
                                    ];
                                }

                                // Add faculty to the subject within the SNO
                                $groupedData[$sno][$subjectCode]['faculties'][] = $facultyName;
                            }

                            // Step 2: Display the data in the table
                            foreach ($groupedData as $sno => $subjects) {
                                $snoRowspan = count($subjects); // Number of subjects for the SNO

                                $isFirstSubject = true; // Track if it's the first subject for rowspan
                                foreach ($subjects as $subjectDetails) {
                                    $subjectName = $subjectDetails['subject_name'];
                                    $facultyList = implode(', ', $subjectDetails['faculties']); // Join faculties with comma

                                    echo "<tr>";
                                    // Display SNO with rowspan only on the first subject row
                                    if ($isFirstSubject) {
                                        echo "<td rowspan='{$snoRowspan}' style='width: 30px; text-align: center; vertical-align:middle;'>{$sno}</td>";
                                        $isFirstSubject = false;
                                    }
                                    // Display subject and faculties
                                    echo "<td>" . e($subjectName) . "</td>";
                                    echo "<td>" . e($facultyList) . "</td>";
                                    echo "</tr>";
                                }
                            }
                        }
                        ?>
                    </tbody>
                </table>

                <br />
                <hr /><br />
                <!-- Display Subject-wise Attendance Below 40% -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject-wise Attendance Above 65% and Less than 75%</th>
                        </tr>
                        <tr>
                            <th style='text-align: center;'>S.No.</th>
                            <th style='text-align: center;'>Count</th>
                            <th>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        ksort($between65And75BySubject);
                        foreach ($between65And75BySubject as $subjectCode => $students) {
                            echo "<tr>";
                            echo "<td style='text-align: center;'>{$subjectCode}</td>";
                            echo "<td style='text-align: center;'>" . count($students) . "</td>";
                            echo "<td>" . implode(', ', $students) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                <hr />
                <!-- Display Subject-wise Attendance Below 40% -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th colspan="3">Subject-wise Attendance Below 65%</th>
                        </tr>
                        <tr>
                            <th style='text-align: center;'>S.No.</th>
                            <th style='text-align: center;'>Count</th>
                            <th>List of Student Roll Numbers</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        ksort($lessThan65BySubject);
                        foreach ($lessThan65BySubject as $subjectCode => $students) {
                            echo "<tr>";
                            echo "<td style='text-align: center;'>{$subjectCode}</td>";
                            echo "<td style='text-align: center;'>" . count($students) . "</td>";
                            echo "<td>" . implode(', ', $students) . "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>

            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>