<?php
// coattainment.class.php - Modular Course Outcome & Program Outcome Attainment Engine
require_once("dbcredentials.class.php");
require_once("logs.class.php");

trait COAttainmentTrait
{
    // Helper to normalize single ID or multiple IDs (array or comma-separated string)
    public function normalizeSubjectIds($subject_id)
    {
        if (is_array($subject_id)) {
            $ids = array_map('intval', $subject_id);
        } elseif (is_string($subject_id) && strpos($subject_id, ',') !== false) {
            $ids = array_map('intval', explode(',', $subject_id));
        } else {
            $ids = [(int)$subject_id];
        }
        $ids = array_filter($ids, function($id) { return $id > 0; });
        return !empty($ids) ? array_values(array_unique($ids)) : [0];
    }

    public function buildInClause($ids)
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return ['sql' => $placeholders, 'params' => $ids];
    }

    public function getSubjectType($subject_id)
    {
        $ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($ids);
        $subQuery = "SELECT sub_type FROM subjects WHERE id IN ({$in['sql']})";
        $subRes = $this->fetchAssoc($subQuery, $in['params']);
        foreach ($subRes as $row) {
            $st = strtolower($row['sub_type'] ?? 'theory');
            if ($st === 'lab' || $st === 'dti') {
                return $st;
            }
        }
        return strtolower($subRes[0]['sub_type'] ?? 'theory');
    }

    /**
     * Resolve active regulation code for the subject(s).
     */
    public function getRegulationForSubject($subject_id): string
    {
        $ids = $this->normalizeSubjectIds($subject_id);
        $subId = $ids[0] ?? 0;
        if ($subId <= 0) {
            return 'R23';
        }
        $query = "SELECT c.reg FROM subjects s JOIN classes c ON s.class_id = c.id WHERE s.id = ?";
        $rows = $this->fetchAssoc($query, [$subId]);
        return !empty($rows[0]['reg']) ? strtoupper(trim($rows[0]['reg'])) : 'R23';
    }

    /**
     * Fetch dynamic target attainment threshold from centralized academic settings.
     */
    public function getTargetAttainmentThreshold($subject_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationForSubject($subject_id) : 'R23';
        return (float)\Services\SettingsService::getInstance()->get('co_target_percentage', $reg, 60.0);
    }

    /**
     * Compute Direct CO Attainment from CIA and SEE components dynamically.
     */
    public function getDirectCoAttainment(float $ciaAttainment, float $seeAttainment, $subject_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationForSubject($subject_id) : 'R23';
        return \Services\SettingsService::getInstance()->computeDirectCoAttainment($ciaAttainment, $seeAttainment, $reg);
    }

    /**
     * Compute Overall PO/PSO Attainment from Direct and Indirect components dynamically.
     */
    public function getOverallPoAttainment(float $directAttainment, float $indirectAttainment, $subject_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationForSubject($subject_id) : 'R23';
        return \Services\SettingsService::getInstance()->computeOverallPoAttainment($directAttainment, $indirectAttainment, $reg);
    }

    /**
     * Calculate Attainment Level (1, 2, 3) from cohort success rate.
     */
    public function getAttainmentLevel(float $cohortPct, $subject_id = null): int
    {
        require_once __DIR__ . '/services/SettingsService.php';
        $reg = $subject_id ? $this->getRegulationForSubject($subject_id) : 'R23';
        return \Services\SettingsService::getInstance()->calculateAttainmentLevel($cohortPct, $reg);
    }

    /**
     * Fetch Course Outcome (CO) Attainment Analysis
     * Returns CO attainment percentages and suggestions based on threshold.
     */
    public function getCOAttainment($subject_id, $assessment_id = null, $component_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            require_once __DIR__ . '/services/SettingsService.php';
            $reg = $this->getRegulationForSubject($subject_id);
            $threshold = $this->getTargetAttainmentThreshold($subject_id);
            $condSubjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_subjective_condensed', $reg, 15.0);
            $condObjectiveMax = (float)\Services\SettingsService::getInstance()->get('theory_mid_objective_marks', $reg, 10.0);
            $condAssignmentMax = (float)\Services\SettingsService::getInstance()->get('theory_assignment_marks', $reg, 5.0);

            // Fetch component totals for scaling across assessments
            $compTotalsQuery = "
                SELECT i.assessment_number, ac.component_type, SUM(q.marks) as total_marks
                FROM assessment_questions q
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
                GROUP BY i.assessment_number, ac.component_type
            ";
            $compTotalRows = $this->fetchAssoc($compTotalsQuery, $in['params']);
            $compFullMarks = [];
            foreach ($compTotalRows as $ctr) {
                $aNum = $ctr['assessment_number'];
                $cType = strtolower(trim($ctr['component_type']));
                $compFullMarks[$aNum][$cType] = (float)$ctr['total_marks'];
            }

            $getCompScale = function($aNum, $cType) use ($compFullMarks, $condSubjectiveMax, $condObjectiveMax, $condAssignmentMax) {
                $c = strtolower(trim($cType));
                if ($c === 'subjective') {
                    return 30.0 > 0 ? ($condSubjectiveMax / 30.0) : 1.0;
                } elseif ($c === 'objective') {
                    $fullObj = $compFullMarks[$aNum]['objective'] ?? 0;
                    if ($fullObj <= 0) $fullObj = $condObjectiveMax;
                    return $fullObj > 0 ? ($condObjectiveMax / $fullObj) : 1.0;
                } elseif ($c === 'assignment') {
                    $fullAsgn = $compFullMarks[$aNum]['assignment'] ?? 0;
                    if ($fullAsgn <= 0) $fullAsgn = $condAssignmentMax;
                    return $fullAsgn > 0 ? ($condAssignmentMax / $fullAsgn) : 1.0;
                }
                return 1.0;
            };

            // Choice-aware calculation for Theory: either/or choice questions (1-2, 3-4, 5-6)
            $where_clauses = ["c.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $where_clauses[] = "(i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }
            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }
            $where_sql = implode(" AND ", $where_clauses);

            // Fetch question mapping and student marks
            $qQuery = "
                SELECT
                    c.id as co_id, c.co_number,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM course_outcomes c
                JOIN question_co_mapping qcm ON c.id = qcm.co_id
                JOIN assessment_questions q ON qcm.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY c.co_number, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            // Structure data per CO -> student -> assessment -> main_question_pair
            $coStudents = [];
            $coLabels = [];
            $coDesc = [];

            // Query CO details for consistent response
            $coInfoQuery = "SELECT id, co_number, co_description FROM course_outcomes WHERE sub_id IN ({$in['sql']}) ORDER BY co_number";
            $coInfoRows = $this->fetchAssoc($coInfoQuery, $in['params']);
            foreach ($coInfoRows as $cr) {
                $coLabels[$cr['id']] = 'CO' . $cr['co_number'];
                $coDesc[$cr['id']] = $cr['co_description'] ?? '';
                $coStudents[$cr['id']] = [];
            }

            foreach ($rawRows as $row) {
                $coId = $row['co_id'];
                $stu_id = $row['stu_id'] ?? 0;
                $aNum = $row['assessment_number'];
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!$stu_id) continue;

                if (!isset($coStudents[$coId][$stu_id])) {
                    $coStudents[$coId][$stu_id] = [
                        'subjective' => [],
                        'other' => []
                    ];
                }

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum])) {
                            $coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum]['obtained'] += $obtained;
                        $coStudents[$coId][$stu_id]['subjective'][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        if (!isset($coStudents[$coId][$stu_id]['other'][$aNum][$comp])) {
                            $coStudents[$coId][$stu_id]['other'][$aNum][$comp] = ['obtained' => 0, 'max' => 0];
                        }
                        $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['obtained'] += $obtained;
                        $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['max'] += $qMarks;
                    }
                } else {
                    if (!isset($coStudents[$coId][$stu_id]['other'][$aNum][$comp])) {
                        $coStudents[$coId][$stu_id]['other'][$aNum][$comp] = ['obtained' => 0, 'max' => 0];
                    }
                    $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['obtained'] += $obtained;
                    $coStudents[$coId][$stu_id]['other'][$aNum][$comp]['max'] += $qMarks;
                }
            }

            // Resolve choice questions and scale components per student per CO
            $results = [];

            foreach ($coStudents as $coId => $students) {
                $coNum = (int)str_replace('CO', '', $coLabels[$coId] ?? '0');
                $totalStudents = count($students);
                $attainedStudents = 0;
                $totalCohortMarks = 0;
                $totalCohortMax = 0;

                foreach ($students as $stu_id => $sData) {
                    $stuObtained = 0;
                    $stuMax = 0;

                    // Process subjective pairs: (1,2), (3,4), (5,6)
                    if (!empty($sData['subjective'])) {
                        foreach ($sData['subjective'] as $aNum => $mainQuestions) {
                            $scale = $getCompScale($aNum, 'subjective');
                            $pairKeys = [];
                            foreach (array_keys($mainQuestions) as $mNum) {
                                $pairKeys[(int)ceil($mNum / 2)] = true;
                            }
                            foreach (array_keys($pairKeys) as $pairKey) {
                                $qOdd = $pairKey * 2 - 1;
                                $qEven = $pairKey * 2;
                                $oddObt = $mainQuestions[$qOdd]['obtained'] ?? 0;
                                $oddMax = $mainQuestions[$qOdd]['max'] ?? 0;
                                $evenObt = $mainQuestions[$qEven]['obtained'] ?? 0;
                                $evenMax = $mainQuestions[$qEven]['max'] ?? 0;

                                $pairObtained = max($oddObt, $evenObt);
                                $pairMax = max($oddMax, $evenMax);

                                $stuObtained += ($pairObtained * $scale);
                                $stuMax += ($pairMax * $scale);
                            }
                        }
                    }

                    // Process other components (objective, assignment, etc.)
                    if (!empty($sData['other'])) {
                        foreach ($sData['other'] as $aNum => $comps) {
                            foreach ($comps as $cType => $cMarks) {
                                $scale = $getCompScale($aNum, $cType);
                                $stuObtained += ($cMarks['obtained'] * $scale);
                                $stuMax += ($cMarks['max'] * $scale);
                            }
                        }
                    }

                    $totalCohortMarks += $stuObtained;
                    $totalCohortMax += $stuMax;

                    if ($stuMax > 0 && ($stuObtained / $stuMax * 100) >= $threshold) {
                        $attainedStudents++;
                    }
                }

                $isAssessed = ($totalStudents > 0 && $totalCohortMax > 0);
                $cohortPercentage = $isAssessed ? round(($totalCohortMarks / $totalCohortMax) * 100, 2) : null;

                $results[] = [
                    'co_id' => $coId,
                    'co_number' => $coNum,
                    'co_label' => $coLabels[$coId] ?? "CO$coNum",
                    'co_description' => $coDesc[$coId] ?? '',
                    'co_attainment_percentage' => $cohortPercentage,
                    'attained_students_count' => $attainedStudents,
                    'total_students_count' => $totalStudents,
                    'is_assessed' => $isAssessed
                ];
            }
        } else {
            // Standard proportional calculation for Lab, DTI, etc.
            $threshold = $this->getTargetAttainmentThreshold($subject_id);
            $query = "
                SELECT
                    c.id as co_id,
                    c.co_number,
                    CONCAT('CO', c.co_number) as co_label,
                    c.co_description,
                    ROUND((SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN m.marks_obtained ELSE 0 END) /
                             NULLIF(SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN q.marks ELSE 0 END), 0)) * 100, 2) AS co_attainment_percentage,
                    COUNT(DISTINCT CASE WHEN (m.marks_obtained/q.marks)*100 >= {$threshold} THEN m.stu_id END) as attained_students_count,
                    COUNT(DISTINCT m.stu_id) as total_students_count
                FROM course_outcomes c
                LEFT JOIN question_co_mapping qcm ON c.id = qcm.co_id
                LEFT JOIN assessment_questions q ON qcm.question_id = q.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                LEFT JOIN assessment_components ac ON q.component_id = ac.id
                LEFT JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE c.sub_id IN ({$in['sql']})
            ";

            $params = array_merge($in['params'], $in['params'], $in['params']);
            $where_clauses = [];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $where_clauses[] = "(i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }

            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }

            if (!empty($where_clauses)) {
                $query .= " AND " . implode(" AND ", $where_clauses);
            }

            $query .= " GROUP BY c.id, c.co_number ORDER BY c.co_number";
            $results = $this->fetchAssoc($query, $params);

            foreach ($results as &$r) {
                $isAssessed = (!empty($r['total_students_count']) && $r['total_students_count'] > 0 && $r['co_attainment_percentage'] !== null);
                $r['is_assessed'] = $isAssessed;
                if (!$isAssessed) {
                    $r['co_attainment_percentage'] = null;
                }
            }
            unset($r);
        }

        // Suggestions based on dynamic threshold from centralized settings
        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['is_assessed'] && $row['co_attainment_percentage'] !== null && $row['co_attainment_percentage'] < $targetThreshold) {
                $suggestions[] = "Attainment for {$row['co_label']} ({$row['co_attainment_percentage']}%) is below the target ({$targetThreshold}%). Consider remedial classes, tutorial sessions, or adjusting teaching strategies for topics related to this CO. Description: '{$row['co_description']}'.";
            }
        }

        if (empty($results)) {
            $suggestions[] = "No CO attainment data found. Ensure questions are mapped to COs and student marks are entered.";
        } elseif (empty($suggestions)) {
            $suggestions[] = "All assessed COs meet or exceed the target attainment threshold of {$targetThreshold}%. Good performance!";
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch Program Outcome (PO) Attainment Analysis
     */
    public function getPOAttainment($subject_id, $assessment_id = null)
    {
        $co_attainment_data_json = $this->getCOAttainment($subject_id, $assessment_id);
        $co_attainment_data = json_decode($co_attainment_data_json, true);

        if (empty($co_attainment_data['data'])) {
            return json_encode(['data' => [], 'suggestions' => ["Cannot calculate PO attainment: No CO attainment data available."]]);
        }

        $co_attainment_map = [];
        $co_num_attainment_map = [];
        foreach ($co_attainment_data['data'] as $co_data) {
            if (!empty($co_data['is_assessed']) && $co_data['co_attainment_percentage'] !== null) {
                $co_attainment_map[$co_data['co_id']] = (float)$co_data['co_attainment_percentage'];
                $co_num_attainment_map[$co_data['co_number']] = (float)$co_data['co_attainment_percentage'];
            }
        }

        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);
        $query_mapping = "
            SELECT
                p.id as po_id, p.code as po_label, p.description as po_description,
                co.co_number, cpm.co_id, cpm.weightage
            FROM co_po_mapping cpm
            JOIN course_outcomes co ON cpm.co_id = co.id
            JOIN po_pso p ON cpm.po_id = p.id
            JOIN subjects s ON co.sub_id = s.id
            JOIN classes cl ON s.class_id = cl.id
            WHERE co.sub_id IN ({$in['sql']})
            GROUP BY p.id, co.co_number
            ORDER BY p.orderid, p.code, co.co_number
        ";

        $params_mapping = $in['params'];
        $mappings = $this->fetchAssoc($query_mapping, $params_mapping);

        if (empty($mappings)) {
            return json_encode(['data' => [], 'suggestions' => ["Cannot calculate PO attainment: No CO-PO mapping found for this subject."]]);
        }

        $po_attainment = [];
        $po_details = [];

        foreach ($mappings as $map) {
            $po_id = $map['po_id'];
            $co_id = $map['co_id'];
            $co_num = $map['co_number'];
            $weightage = (float)($map['weightage'] ?? 1.0);

            if (!isset($po_details[$po_id])) {
                $po_details[$po_id] = ['label' => $map['po_label'], 'description' => $map['po_description']];
                $po_attainment[$po_id] = ['total_weighted_attainment' => 0, 'total_weightage' => 0, 'contributing_co_count' => 0];
            }

            if (isset($co_attainment_map[$co_id])) {
                $co_attainment_percentage = $co_attainment_map[$co_id];
                $po_attainment[$po_id]['total_weighted_attainment'] += ($co_attainment_percentage * $weightage);
                $po_attainment[$po_id]['total_weightage'] += $weightage;
                $po_attainment[$po_id]['contributing_co_count']++;
            } elseif (isset($co_num_attainment_map[$co_num])) {
                $co_attainment_percentage = $co_num_attainment_map[$co_num];
                $po_attainment[$po_id]['total_weighted_attainment'] += ($co_attainment_percentage * $weightage);
                $po_attainment[$po_id]['total_weightage'] += $weightage;
                $po_attainment[$po_id]['contributing_co_count']++;
            }
        }

        $po_results = [];
        $suggestions = [];
        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);

        foreach ($po_attainment as $po_id => $data) {
            $isAssessed = ($data['total_weightage'] > 0 && $data['contributing_co_count'] > 0);
            $final_attainment = $isAssessed
                ? round($data['total_weighted_attainment'] / $data['total_weightage'], 2)
                : null;

            $po_results[] = [
                'po_label' => $po_details[$po_id]['label'],
                'po_description' => $po_details[$po_id]['description'],
                'po_attainment_percentage' => $final_attainment,
                'contributing_co_count' => $data['contributing_co_count'],
                'is_assessed' => $isAssessed
            ];

            if ($isAssessed && $final_attainment !== null && $final_attainment < $targetThreshold) {
                $suggestions[] = "Attainment for {$po_details[$po_id]['label']} ({$final_attainment}%) is below the target ({$targetThreshold}%). Review the attainment of contributing COs for insights. PO Description: '{$po_details[$po_id]['description']}'.";
            }
        }

        if (empty($po_results)) {
            $suggestions[] = "PO attainment calculated, but contributing COs lack assessment data.";
        } elseif (empty($suggestions)) {
            $suggestions[] = "PO attainment analysis complete. All assessed POs meet or exceed target threshold.";
        }

        return json_encode(['data' => $po_results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch data for CO-PO Matrix/Table
     */
    public function getCoPoMatrixData($subject_id, $assessment_id = null)
    {
        $co_attainment_json = $this->getCOAttainment($subject_id, $assessment_id);
        $co_attainment_data = json_decode($co_attainment_json, true);
        $co_map = [];
        $co_num_map = [];
        if (!empty($co_attainment_data['data'])) {
            foreach ($co_attainment_data['data'] as $co) {
                $co_map[$co['co_id']] = $co['co_attainment_percentage'];
                $co_num_map[$co['co_number']] = $co['co_attainment_percentage'];
            }
        } else {
            return json_encode(['data' => [], 'suggestions' => ['No CO attainment data available to build CO-PO Matrix.']]);
        }

        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);
        $query_mapping = "
            SELECT
                co.id as co_id, co.co_number, CONCAT('CO', co.co_number) as co_label,
                p.id as po_id, p.code as po_label, cpm.weightage
            FROM course_outcomes co
            LEFT JOIN co_po_mapping cpm ON co.id = cpm.co_id
            LEFT JOIN po_pso p ON cpm.po_id = p.id
            JOIN subjects s ON co.sub_id = s.id
            JOIN classes cl ON s.class_id = cl.id
            WHERE co.sub_id IN ({$in['sql']})
            GROUP BY co.co_number, p.id
            ORDER BY co.co_number, p.orderid, p.code
        ";
        $params_mapping = $in['params'];
        $mappings = $this->fetchAssoc($query_mapping, $params_mapping);

        $matrix_data = [];
        foreach ($mappings as $map) {
            $co_id = $map['co_id'];
            $co_num = $map['co_number'];
            $attainment = $co_map[$co_id] ?? ($co_num_map[$co_num] ?? 'N/A');
            if ($attainment === null) {
                $attainment = 'N/A';
            }
            $matrix_data[] = [
                'co_label' => $map['co_label'],
                'co_attainment' => $attainment,
                'po_label' => $map['po_label'] ?? 'Not Mapped',
                'weightage' => $map['weightage'] ?? '-'
            ];
        }

        $suggestions = [];
        if (empty($matrix_data)) {
            $suggestions[] = "No CO-PO mapping data found for this subject.";
        } else {
            $suggestions[] = "CO-PO Matrix data loaded. Shows CO attainment and linked POs. 'N/A' indicates CO attainment data is missing for that CO.";
        }

        return json_encode(['data' => $matrix_data, 'suggestions' => $suggestions]);
    }

    public function getCOVisualSummary($subject_id, $assessment_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            $coAttDataJson = $this->getCOAttainment($subject_id, $assessment_id);
            $coAttData = json_decode($coAttDataJson, true);
            $results = [];

            $stuCountQuery = "
                SELECT COUNT(DISTINCT m.stu_id) AS student_count
                FROM student_marks m
                JOIN assessment_questions q ON m.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
            ";
            $stuParams = $in['params'];
            if ($assessment_id && $assessment_id !== 'all') {
                $stuCountQuery .= " AND i.assessment_number = ?";
                $stuParams[] = $assessment_id;
            } else {
                $stuCountQuery .= " AND (i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }
            $stuRes = $this->fetchAssoc($stuCountQuery, $stuParams);
            $stuCount = $stuRes[0]['student_count'] ?? 0;

            if (!empty($coAttData['data'])) {
                foreach ($coAttData['data'] as $co) {
                    $results[] = [
                        'co_label' => $co['co_label'],
                        'avg_attainment' => $co['co_attainment_percentage'],
                        'student_count' => $co['total_students_count'] ?? $stuCount,
                        'is_assessed' => $co['is_assessed'] ?? ($co['co_attainment_percentage'] !== null)
                    ];
                }
            }
        } else {
            $query = "
                SELECT
                    CONCAT('CO', c.co_number) AS co_label,
                    ROUND(AVG((m.marks_obtained/q.marks)*100), 2) AS avg_attainment,
                    COUNT(DISTINCT m.stu_id) AS student_count
                FROM course_outcomes c
                JOIN question_co_mapping qcm ON c.id = qcm.co_id
                JOIN assessment_questions q ON qcm.question_id = q.id
                JOIN student_marks m ON q.id = m.question_id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE c.sub_id IN ({$in['sql']})
            ";
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $query .= " AND i.assessment_number = ?";
                $params[] = $assessment_id;
            } else {
                $query .= " AND (i.assessment_number IS NULL OR i.assessment_number != 'SEE')";
            }

            $query .= " GROUP BY c.co_number ORDER BY c.co_number";
            $results = $this->fetchAssoc($query, $params);

            foreach ($results as &$r) {
                $isAssessed = (!empty($r['student_count']) && $r['student_count'] > 0 && $r['avg_attainment'] !== null);
                $r['is_assessed'] = $isAssessed;
                if (!$isAssessed) {
                    $r['avg_attainment'] = null;
                }
            }
            unset($r);
        }

        $targetThreshold = $this->getTargetAttainmentThreshold($subject_id);
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['is_assessed'] && $row['avg_attainment'] !== null && $row['avg_attainment'] < $targetThreshold) {
                $suggestions[] = "CO {$row['co_label']} average ({$row['avg_attainment']}%) is below target (" . $targetThreshold . "%). Consider remedial support.";
            }
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Helper function to execute query and fetch associative array
     */
    public function fetchAssoc($query, $params)
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            return [];
        }

        if (!empty($params)) {
            $types = str_repeat('i', count($params));
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            $stmt->close();
            return [];
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }

        $stmt->close();
        return $data;
    }
}

class COAttainmentEngine extends DBCredentials
{
    use COAttainmentTrait;

    public function __construct()
    {
        parent::__construct();
    }
}
