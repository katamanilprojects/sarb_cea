<?php
if (!empty($_POST["cls_id"]) && !empty($_POST["action"]) && $_POST["action"] == "hod_export_cia_marks" || $_POST["action"] == "admin_export_cia_marks") {
    if ($_POST["action"] == "hod_export_cia_marks") {
        $redirect_page = "hodshowallclsattendance.php";
    } else {
        $redirect_page = "adminshowallclsattendance.php";
    }


    $cls_id = $_POST["cls_id"];

    require_once("hod.class.php");
    $hodObj = new HOD();

    require_once("faculty.class.php");
    $facultyObj = new Faculty();


    $studentList = $hodObj->getStudentsByClass($cls_id);
    $subjectList = $hodObj->getSubjectsByClassID($cls_id);

    if (empty($studentList) || empty($subjectList['data'])) {
        echo "<script>
            alert('No data available for this class.');
            window.location.href = '" . $redirect_page . "';
        </script>";
        exit;
    }


    $ciaMarksData = [];

    $noofsubjects = 0;

    foreach ($subjectList['data'] as $subject) {
        $selected_sub_id = $subject['id'];
        $assessmentDetails = [];
        // Check if both assessments are present

        require_once("modulecheckcia.php");

        if ($includeInternalMarks) {
            if (!empty($prg_res['prg_code'])) {
                if ($prg_res['prg_code'] == "A") {
                    if ($prg_res['sub_type'] == "project") {
                        foreach ($studentList as $student) {
                            $total = ($student['marks'][1]['component1_marks'] !== null)
                                ? round($student['marks'][1]['component1_marks'] + $student['marks'][1]['component2_marks'])
                                : null;
                            if ($total !== null) {
                                $noofsubjects++;
                                $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $total;
                            }
                        }
                    } elseif ($prg_res['sub_type'] == "lab" || $prg_res['sub_type'] == "dti") {
                        foreach ($studentList as $student) {
                            $total = ($student['marks'][1]['day_to_day_marks'] !== null)
                                ? round($student['marks'][1]['day_to_day_marks'] + $student['marks'][1]['internal_test_marks'])
                                : null;
                            if ($total !== null) {
                                $noofsubjects++;
                                $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $total;
                            }
                        }
                    } else {
                    $sno = 1;

                    foreach ($studentList as $student) {
                        $assessment1Total = $student['marks'][1]['subjective_marks'] + $student['marks'][1]['objective_marks'] + $student['marks'][1]['assignment_marks'];

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
                        }
                        if (isset($totalCIA)) {
                            $noofsubjects++;
                            $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $totalCIA;
                        }
                    }
                    } // end else (theory)
                } else {

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
                        }

                        if (isset($totalInt) && $student['marks'][3]['marks'] != null) {
                            $totalCIA = $totalInt + $stmarks3;
                        }
                        if (isset($totalCIA)) {
                            $noofsubjects++;
                            $ciaMarksData[$student["roll_number"]][$selected_sub_id] = $totalCIA;
                        }
                    }
                    unset($totalCIA);
                }
            }
        }
    }


    if ($noofsubjects > 0) {

        $filename = "CIA_Marks_Class_" . $cls_id . "_Export_" . date("Y-m-d") . ".csv";
        header("Content-Type: text/csv");
        header("Content-Disposition: attachment;filename=$filename");

        $output = fopen("php://output", "w");


        $headers = ["Student Admission No."];
        foreach ($subjectList['data'] as $subject) {
            $headers[] = $subject['subcode'];
        }
        fputcsv($output, $headers);

        foreach ($studentList as $student) {
            $username = $student["roll_number"];
            $row = [$username];
            foreach ($subjectList['data'] as $subject) {
                $subjectid = $subject['id'];
                $row[] = isset($ciaMarksData[$username][$subjectid]) ? $ciaMarksData[$username][$subjectid] : "";
            }
            fputcsv($output, $row);
        }

        fclose($output);
        exit;
    } else {
        echo "<script>
            alert('No Subjects in this class has entered all CIA marks');
            window.location.href = '" . $redirect_page . "';
        </script>";
        exit;
    }
} else {
    // echo "Invalid request.";
}
