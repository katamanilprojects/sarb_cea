<?php
// seeassessment.class.php - Modular Semester End Examination (SEE) & Attainment Architecture
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait SEEAssessmentTrait
{
    protected $seeClassname = "SEEAssessment";

    /**
     * Helper to resolve regulation for subject
     */
    protected function resolveRegulation($sub_id): string
    {
        if (method_exists($this, 'getRegulationForSubject')) {
            return $this->getRegulationForSubject($sub_id);
        }
        require_once("ciamarks.class.php");
        $m = new CIAMarks();
        return $m->getRegulationForSubject($sub_id);
    }

    /**
     * Helper to resolve academic setting
     */
    protected function resolveAcademicSetting($key, $reg, $sub_id, $default)
    {
        if (method_exists($this, 'getAcademicSetting')) {
            return $this->getAcademicSetting($key, $reg, $sub_id, $default);
        }
        require_once("ciamarks.class.php");
        $m = new CIAMarks();
        return $m->getAcademicSetting($key, $reg, $sub_id, $default);
    }

    /**
     * Helper to resolve questions by component
     */
    protected function resolveQuestionsByComponent($component_id)
    {
        if (method_exists($this, 'getQuestionsByComponent')) {
            return $this->getQuestionsByComponent($component_id);
        }
        require_once("assessmentstructure.class.php");
        $s = new AssessmentStructure();
        return $s->getQuestionsByComponent($component_id);
    }

    /**
     * Helper to resolve COs by subject ID
     */
    protected function resolveCOsBySubjectId($sub_id)
    {
        if (method_exists($this, 'getCOsBySubjectId')) {
            return $this->getCOsBySubjectId($sub_id);
        }
        require_once("courseoutcome.class.php");
        $co = new CourseOutcome();
        return $co->getCOsBySubjectId($sub_id);
    }

    /**
     * Return external QP templates conforming to autonomous regulations
     */
    public function getSEETemplates()
    {
        return [
            'see_theory_70' => [
                'id' => 'see_theory_70',
                'name' => 'SEE Regular Theory (70 Marks)',
                'total_marks' => 70.0,
                'q1_subparts' => 10,
                'q1_marks_each' => 2.0,
                'q1_total' => 20.0,
                'choice_groups' => [
                    ['label' => '2 or 3', 'pair' => ['2', '3'], 'marks' => 10.0],
                    ['label' => '4 or 5', 'pair' => ['4', '5'], 'marks' => 10.0],
                    ['label' => '6 or 7', 'pair' => ['6', '7'], 'marks' => 10.0],
                    ['label' => '8 or 9', 'pair' => ['8', '9'], 'marks' => 10.0],
                    ['label' => '10 or 11', 'pair' => ['10', '11'], 'marks' => 10.0],
                ],
                'types' => ['Question', 'Short_Answer', 'Analytical', 'Problem', 'Derivation']
            ],
            'see_split_35' => [
                'id' => 'see_split_35',
                'name' => 'SEE Composite Course Split (35 Marks)',
                'total_marks' => 35.0,
                'q1_subparts' => 5,
                'q1_marks_each' => 1.0,
                'q1_total' => 5.0,
                'choice_groups' => [
                    ['label' => '2 or 3', 'pair' => ['2', '3'], 'marks' => 10.0],
                    ['label' => '4 or 5', 'pair' => ['4', '5'], 'marks' => 10.0],
                    ['label' => '6 or 7', 'pair' => ['6', '7'], 'marks' => 10.0],
                ],
                'types' => ['Question', 'Short_Answer', 'Analytical', 'Problem']
            ]
        ];
    }

    /**
     * Retrieve or create SEE assessment record in internal_assessments
     */
    public function getOrCreateSEEAssessment($sub_id, $assessment_number = 'SEE')
    {
        $stmt = $this->conn->prepare("SELECT id FROM internal_assessments WHERE sub_id = ? AND assessment_number = ? LIMIT 1");
        $stmt->bind_param("is", $sub_id, $assessment_number);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($res) {
            return intval($res['id']);
        }

        $stmtInsert = $this->conn->prepare("INSERT INTO internal_assessments (sub_id, assessment_number) VALUES (?, ?)");
        $stmtInsert->bind_param("is", $sub_id, $assessment_number);
        $stmtInsert->execute();
        $newId = $stmtInsert->insert_id;
        $stmtInsert->close();

        return intval($newId);
    }

    /**
     * Retrieve or create SEE assessment component
     */
    public function getOrCreateSEEComponent($sub_id, $component_type = 'SEE-Theory', $assessment_number = 'SEE')
    {
        $assessment_id = $this->getOrCreateSEEAssessment($sub_id, $assessment_number);
        
        $stmt = $this->conn->prepare("SELECT id, assessment_id, component_type, sequence_number FROM assessment_components WHERE assessment_id = ? AND component_type = ? LIMIT 1");
        $stmt->bind_param("is", $assessment_id, $component_type);
        $stmt->execute();
        $comp = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($comp) {
            return $comp;
        }

        $stmtInsert = $this->conn->prepare("INSERT INTO assessment_components (assessment_id, component_type, sequence_number) VALUES (?, ?, 1)");
        $stmtInsert->bind_param("is", $assessment_id, $component_type);
        $stmtInsert->execute();
        $newCompId = $stmtInsert->insert_id;
        $stmtInsert->close();

        return [
            'id' => $newCompId,
            'assessment_id' => $assessment_id,
            'component_type' => $component_type,
            'sequence_number' => 1
        ];
    }

    /**
     * Check if SEE question paper metadata has been populated
     */
    public function isSEEMetadataConfigured($sub_id, $component_type = 'SEE-Theory')
    {
        $comp = $this->getOrCreateSEEComponent($sub_id, $component_type);
        if (!$comp || empty($comp['id'])) {
            return false;
        }
        $questions = $this->resolveQuestionsByComponent($comp['id']);
        return !empty($questions) && count($questions) >= 11;
    }

    /**
     * Check if finalized SEE marks exist in external_assessment_marks
     */
    public function isSEEMarksSubmitted($sub_id)
    {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as total FROM external_assessment_marks WHERE subject_id = ?");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return (!empty($row['total']) && $row['total'] > 0);
    }

    /**
     * Fetch detailed student question scores for a specific assessment component
     */
    public function getComponentQuestionMarks($student_id, $component_id)
    {
        $stmt = $this->conn->prepare("
            SELECT aq.question_label, sm.marks_obtained, aq.marks as max_marks
            FROM assessment_questions aq
            LEFT JOIN student_marks sm ON sm.question_id = aq.id AND sm.stu_id = ?
            WHERE aq.component_id = ?
        ");
        $stmt->bind_param("ii", $student_id, $component_id);
        $stmt->execute();
        $res = $stmt->get_result();
        $marks = [];
        while ($row = $res->fetch_assoc()) {
            $marks[$row['question_label']] = [
                'obtained' => ($row['marks_obtained'] !== null) ? floatval($row['marks_obtained']) : null,
                'max' => floatval($row['max_marks'])
            ];
        }
        $stmt->close();
        return $marks;
    }

    /**
     * Execute Either/Or choice resolution and consolidate SEE marks for all mapped students
     */
    public function consolidateSEEMarks($sub_id, $component_type = 'SEE-Theory')
    {
        require_once("faculty.class.php");
        $facultyObj = new Faculty();
        $students = $facultyObj->getMappedStudents($sub_id);
        $comp = $this->getOrCreateSEEComponent($sub_id, $component_type);
        $reg = $this->resolveRegulation($sub_id);

        $maxSeeMarks = (float)$this->resolveAcademicSetting('theory_see_max_marks', $reg, $sub_id, 70.0);
        $q1CompulsoryMarks = (float)$this->resolveAcademicSetting('theory_compulsory_q1_marks', $reg, $sub_id, 20.0);
        $choiceGroupMarks = (float)$this->resolveAcademicSetting('theory_choice_group_marks', $reg, $sub_id, 10.0);

        $isSplit = ($component_type === 'SEE-Split-A' || $component_type === 'SEE-Split-B');
        if ($isSplit) {
            $maxSeeMarks = (float)$this->resolveAcademicSetting('composite_split_max_marks', $reg, $sub_id, 35.0);
            $q1CompulsoryMarks = (float)$this->resolveAcademicSetting('composite_q1_marks', $reg, $sub_id, 5.0);
        }

        $templates = $this->getSEETemplates();
        $template = $isSplit ? $templates['see_split_35'] : $templates['see_theory_70'];

        $consolidated = [];

        foreach ($students as $student) {
            $stuId = $student['id'];
            $qMarks = $this->getComponentQuestionMarks($stuId, $comp['id']);

            // 1. Resolve Compulsory Question 1 (1a..1j or 1a..1e)
            $q1Total = 0.0;
            $q1PartsCount = $template['q1_subparts'];
            $alpha = range('a', 'z');
            for ($p = 0; $p < $q1PartsCount; $p++) {
                $label = '1' . $alpha[$p];
                if (isset($qMarks[$label]['obtained']) && $qMarks[$label]['obtained'] !== null) {
                    $q1Total += $qMarks[$label]['obtained'];
                }
            }
            $q1Final = min($q1Total, $q1CompulsoryMarks);

            // 2. Resolve Choice Groups: Group Score = max(Total(Q_OptA), Total(Q_OptB))
            $groupResults = [];
            $totalChoiceMarks = 0.0;

            foreach ($template['choice_groups'] as $gIndex => $grp) {
                $parentA = $grp['pair'][0];
                $parentB = $grp['pair'][1];

                $sumA = 0.0;
                $hasAttemptA = false;
                foreach ($qMarks as $qLabel => $data) {
                    if (strcasecmp($qLabel, $parentA) === 0 || preg_match('/^' . preg_quote($parentA, '/') . '[a-z]$/i', $qLabel)) {
                        if ($data['obtained'] !== null) {
                            $sumA += $data['obtained'];
                            $hasAttemptA = true;
                        }
                    }
                }

                $sumB = 0.0;
                $hasAttemptB = false;
                foreach ($qMarks as $qLabel => $data) {
                    if (strcasecmp($qLabel, $parentB) === 0 || preg_match('/^' . preg_quote($parentB, '/') . '[a-z]$/i', $qLabel)) {
                        if ($data['obtained'] !== null) {
                            $sumB += $data['obtained'];
                            $hasAttemptB = true;
                        }
                    }
                }

                $validA = min($sumA, $choiceGroupMarks);
                $validB = min($sumB, $choiceGroupMarks);
                $bestGroup = max($validA, $validB);

                $groupResults[$gIndex + 1] = [
                    'parent_a' => $parentA,
                    'parent_b' => $parentB,
                    'sum_a' => $validA,
                    'sum_b' => $validB,
                    'resolved' => $bestGroup,
                    'chosen' => ($validA >= $validB && $hasAttemptA) ? $parentA : ($hasAttemptB ? $parentB : 'None')
                ];

                $totalChoiceMarks += $bestGroup;
            }

            $finalScore = min($q1Final + $totalChoiceMarks, $maxSeeMarks);

            $consolidated[$stuId] = [
                'student_id' => $stuId,
                'username' => $student['username'],
                'name' => $student['name'],
                'q1_marks' => round($q1Final, 2),
                'groups' => $groupResults,
                'choice_marks' => round($totalChoiceMarks, 2),
                'final_external' => round($finalScore, 2),
                'max_marks' => $maxSeeMarks
            ];
        }

        return $consolidated;
    }

    /**
     * Save consolidated Mode A marks into external_assessment_marks
     */
    public function saveConsolidatedSEEMarks($sub_id, array $marksData, $faculty_id, $entry_mode = 'DETAILED')
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO external_assessment_marks 
                    (student_id, subject_id, entry_mode, external_marks, max_marks, q1_marks, choice_marks, submitted_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    entry_mode = VALUES(entry_mode),
                    external_marks = VALUES(external_marks),
                    max_marks = VALUES(max_marks),
                    q1_marks = VALUES(q1_marks),
                    choice_marks = VALUES(choice_marks),
                    submitted_by = VALUES(submitted_by),
                    submitted_at = CURRENT_TIMESTAMP
            ");

            foreach ($marksData as $row) {
                $stuId = intval($row['student_id']);
                $finalMarks = floatval($row['final_external']);
                $maxMarks = floatval($row['max_marks']);
                $q1Marks = isset($row['q1_marks']) ? floatval($row['q1_marks']) : null;
                $choiceMarks = isset($row['choice_marks']) ? floatval($row['choice_marks']) : null;

                $stmt->bind_param("iisddddi", $stuId, $sub_id, $entry_mode, $finalMarks, $maxMarks, $q1Marks, $choiceMarks, $faculty_id);
                if (!$stmt->execute()) {
                    throw new Exception("Error saving marks for student ID: " . $stuId . " - " . $stmt->error);
                }
            }
            $stmt->close();
            $this->conn->commit();
            return ['status' => 1, 'msg' => 'SEE Marks consolidated and committed successfully.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => 0, 'err' => $e->getMessage()];
        }
    }

    /**
     * Save direct Mode B ledger marks into external_assessment_marks
     */
    public function saveDirectSEEMarks($sub_id, array $studentMarks, $max_marks, $faculty_id)
    {
        $this->conn->begin_transaction();
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO external_assessment_marks 
                    (student_id, subject_id, entry_mode, external_marks, max_marks, submitted_by)
                VALUES (?, ?, 'DIRECT', ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    entry_mode = 'DIRECT',
                    external_marks = VALUES(external_marks),
                    max_marks = VALUES(max_marks),
                    q1_marks = NULL,
                    choice_marks = NULL,
                    submitted_by = VALUES(submitted_by),
                    submitted_at = CURRENT_TIMESTAMP
            ");

            foreach ($studentMarks as $stuId => $score) {
                $stuIdInt = intval($stuId);
                $scoreVal = floatval($score);
                if ($scoreVal < 0 || $scoreVal > $max_marks) {
                    throw new Exception("Marks must be between 0 and $max_marks for student ID $stuIdInt.");
                }
                $stmt->bind_param("iiddi", $stuIdInt, $sub_id, $scoreVal, $max_marks, $faculty_id);
                if (!$stmt->execute()) {
                    throw new Exception("Execution failed for student ID: " . $stuIdInt . " - " . $stmt->error);
                }
            }
            $stmt->close();
            $this->conn->commit();
            return ['status' => 1, 'msg' => 'Direct SEE Marks recorded successfully.'];
        } catch (Exception $e) {
            $this->conn->rollback();
            return ['status' => 0, 'err' => $e->getMessage()];
        }
    }

    /**
     * Retrieve saved external marks joined with student master records
     */
    public function getSavedSEEMarks($sub_id)
    {
        $stmt = $this->conn->prepare("
            SELECT eam.*, s.username, u.name 
            FROM external_assessment_marks eam
            JOIN students s ON s.id = eam.student_id
            LEFT JOIN users u ON u.username = s.username
            WHERE eam.subject_id = ?
            ORDER BY s.username ASC
        ");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $res;
    }

    /**
     * Calculate External (SEE) Course Outcome Attainment
     */
    public function calculateSEECOAttainment($sub_id, $component_type = 'SEE-Theory')
    {
        $reg = $this->resolveRegulation($sub_id);
        $coTargetPct = (float)$this->resolveAcademicSetting('co_target_percentage', $reg, $sub_id, 60.0);
        $l3Threshold = (float)$this->resolveAcademicSetting('attainment_level_3_threshold', $reg, $sub_id, 70.0);
        $l2Threshold = (float)$this->resolveAcademicSetting('attainment_level_2_threshold', $reg, $sub_id, 60.0);
        $l1Threshold = (float)$this->resolveAcademicSetting('attainment_level_1_threshold', $reg, $sub_id, 50.0);

        $cosResult = $this->resolveCOsBySubjectId($sub_id);
        $cos = !empty($cosResult['data']) ? $cosResult['data'] : [];
        if (empty($cos)) {
            return ['status' => 0, 'err' => 'No Course Outcomes configured for this course.'];
        }

        $savedMarks = $this->getSavedSEEMarks($sub_id);
        if (empty($savedMarks)) {
            return ['status' => 0, 'pending' => true, 'err' => 'External SEE marks have not been submitted yet.'];
        }

        $totalStudents = count($savedMarks);
        $entryMode = $savedMarks[0]['entry_mode'];
        $coAttainment = [];

        if ($entryMode === 'DETAILED') {
            $comp = $this->getOrCreateSEEComponent($sub_id, $component_type);
            $templates = $this->getSEETemplates();
            $isSplit = ($component_type === 'SEE-Split-A' || $component_type === 'SEE-Split-B');
            $template = $isSplit ? $templates['see_split_35'] : $templates['see_theory_70'];

            $stmt = $this->conn->prepare("
                SELECT aq.id, aq.question_label, aq.marks, qcm.co_id
                FROM assessment_questions aq
                JOIN question_co_mapping qcm ON qcm.question_id = aq.id
                WHERE aq.component_id = ?
            ");
            $stmt->bind_param("i", $comp['id']);
            $stmt->execute();
            $allQRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmt->close();

            $stmtSm = $this->conn->prepare("
                SELECT sm.stu_id, sm.question_id, sm.marks_obtained
                FROM student_marks sm
                JOIN assessment_questions aq ON aq.id = sm.question_id
                WHERE aq.component_id = ?
            ");
            $stmtSm->bind_param("i", $comp['id']);
            $stmtSm->execute();
            $smRows = $stmtSm->get_result()->fetch_all(MYSQLI_ASSOC);
            $stmtSm->close();

            $stuMarksMap = [];
            foreach ($smRows as $r) {
                $stuMarksMap[intval($r['stu_id'])][intval($r['question_id'])] = floatval($r['marks_obtained']);
            }

            foreach ($cos as $co) {
                $coId = intval($co['id']);
                $coQuestions = array_values(array_filter($allQRows, function($q) use ($coId) {
                    return intval($q['co_id']) === $coId;
                }));

                if (empty($coQuestions)) {
                    $coAttainment[$co['co_number']] = [
                        'co_id' => $coId,
                        'attained_students' => 0,
                        'total_students' => $totalStudents,
                        'cohort_percentage' => 0.0,
                        'attainment_level' => 0,
                        'unmapped' => true
                    ];
                    continue;
                }

                $attainedCount = 0;
                foreach ($savedMarks as $stu) {
                    $stuId = intval($stu['student_id']);
                    $stuQMarks = $stuMarksMap[$stuId] ?? [];

                    $coObtained = 0.0;
                    $coMax = 0.0;

                    foreach ($coQuestions as $q) {
                        $label = strtolower(trim($q['question_label']));
                        if (preg_match('/^1[a-z]$/', $label)) {
                            $coMax += floatval($q['marks']);
                            if (isset($stuQMarks[intval($q['id'])])) {
                                $coObtained += $stuQMarks[intval($q['id'])];
                            }
                        }
                    }

                    foreach ($template['choice_groups'] as $grp) {
                        $parentA = $grp['pair'][0];
                        $parentB = $grp['pair'][1];

                        $sumA_obt = 0.0;
                        $sumA_max = 0.0;
                        $sumB_obt = 0.0;
                        $sumB_max = 0.0;

                        foreach ($coQuestions as $q) {
                            $label = trim($q['question_label']);
                            $qId = intval($q['id']);
                            if (strcasecmp($label, $parentA) === 0 || preg_match('/^' . preg_quote($parentA, '/') . '[a-z]$/i', $label)) {
                                $sumA_max += floatval($q['marks']);
                                if (isset($stuQMarks[$qId])) {
                                    $sumA_obt += $stuQMarks[$qId];
                                }
                            }
                            if (strcasecmp($label, $parentB) === 0 || preg_match('/^' . preg_quote($parentB, '/') . '[a-z]$/i', $label)) {
                                $sumB_max += floatval($q['marks']);
                                if (isset($stuQMarks[$qId])) {
                                    $sumB_obt += $stuQMarks[$qId];
                                }
                            }
                        }

                        $validA_obt = min($sumA_obt, floatval($grp['marks']));
                        $validB_obt = min($sumB_obt, floatval($grp['marks']));
                        $validA_max = min($sumA_max, floatval($grp['marks']));
                        $validB_max = min($sumB_max, floatval($grp['marks']));

                        $coObtained += max($validA_obt, $validB_obt);
                        $coMax += max($validA_max, $validB_max);
                    }

                    $pct = ($coMax > 0) ? ($coObtained / $coMax) * 100.0 : 0.0;
                    if ($pct >= $coTargetPct) {
                        $attainedCount++;
                    }
                }

                $cohortPct = ($totalStudents > 0) ? ($attainedCount / $totalStudents) * 100.0 : 0.0;
                $level = 0;
                if ($cohortPct >= $l3Threshold) $level = 3;
                elseif ($cohortPct >= $l2Threshold) $level = 2;
                elseif ($cohortPct >= $l1Threshold) $level = 1;

                $coAttainment[$co['co_number']] = [
                    'co_id' => $coId,
                    'attained_students' => $attainedCount,
                    'total_students' => $totalStudents,
                    'cohort_percentage' => round($cohortPct, 2),
                    'attainment_level' => $level,
                    'unmapped' => false
                ];
            }
        } else {
            $attainedStudents = 0;
            foreach ($savedMarks as $row) {
                $pct = ($row['max_marks'] > 0) ? (floatval($row['external_marks']) / floatval($row['max_marks'])) * 100.0 : 0.0;
                if ($pct >= $coTargetPct) {
                    $attainedStudents++;
                }
            }
            $cohortPct = ($totalStudents > 0) ? ($attainedStudents / $totalStudents) * 100.0 : 0.0;
            $level = 0;
            if ($cohortPct >= $l3Threshold) $level = 3;
            elseif ($cohortPct >= $l2Threshold) $level = 2;
            elseif ($cohortPct >= $l1Threshold) $level = 1;

            foreach ($cos as $co) {
                $coAttainment[$co['co_number']] = [
                    'co_id' => $co['id'],
                    'attained_students' => $attainedStudents,
                    'total_students' => $totalStudents,
                    'cohort_percentage' => round($cohortPct, 2),
                    'attainment_level' => $level,
                    'unmapped' => false
                ];
            }
        }

        return ['status' => 1, 'mode' => $entryMode, 'data' => $coAttainment];
    }

    /**
     * Calculate Internal (CIA) Course Outcome Attainment
     */
    public function calculateCIACOAttainment($sub_id)
    {
        $reg = $this->resolveRegulation($sub_id);
        $l3Threshold = (float)$this->resolveAcademicSetting('attainment_level_3_threshold', $reg, $sub_id, 70.0);
        $l2Threshold = (float)$this->resolveAcademicSetting('attainment_level_2_threshold', $reg, $sub_id, 60.0);
        $l1Threshold = (float)$this->resolveAcademicSetting('attainment_level_1_threshold', $reg, $sub_id, 50.0);

        require_once("faculty.class.php");
        $facultyObj = new Faculty();
        $students = $facultyObj->getMappedStudents($sub_id);
        $totalStudents = count($students);

        require_once("facciaanalysis2.class.php");
        $analysisObj = new COAnalysis();
        $coJson = $analysisObj->getCOAttainment($sub_id);
        $coData = json_decode($coJson, true);

        if (!empty($coData['data'])) {
            $coAttainment = [];
            foreach ($coData['data'] as $row) {
                $cohortPct = floatval($row['co_attainment_percentage']);
                $level = 0;
                if ($cohortPct >= $l3Threshold) $level = 3;
                elseif ($cohortPct >= $l2Threshold) $level = 2;
                elseif ($cohortPct >= $l1Threshold) $level = 1;

                $coAttainment[$row['co_number']] = [
                    'co_id' => $row['co_id'],
                    'attained_students' => 0,
                    'total_students' => $totalStudents,
                    'cohort_percentage' => round($cohortPct, 2),
                    'attainment_level' => $level,
                    'unmapped' => false
                ];
            }
            return ['status' => 1, 'data' => $coAttainment];
        }

        $cosResult = $this->resolveCOsBySubjectId($sub_id);
        $cos = !empty($cosResult['data']) ? $cosResult['data'] : [];
        if (empty($cos)) {
            return ['status' => 0, 'err' => 'No Course Outcomes configured for this course.'];
        }

        $coAttainment = [];
        foreach ($cos as $co) {
            $coAttainment[$co['co_number']] = [
                'co_id' => $co['id'],
                'attained_students' => 0,
                'total_students' => $totalStudents,
                'cohort_percentage' => 0.0,
                'attainment_level' => 0,
                'unmapped' => true
            ];
        }
        return ['status' => 1, 'data' => $coAttainment];
    }
}

class SEEAssessment extends DBCredentials
{
    use SEEAssessmentTrait;

    public function __construct()
    {
        parent::__construct();
    }
}
