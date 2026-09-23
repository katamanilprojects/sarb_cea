<?php
require_once("dbcredentials.class.php");

class COAnalysis extends DBCredentials
{
    // Define a target threshold for suggestions
    private const TARGET_ATTAINMENT_THRESHOLD = 60;

    public function __construct()
    {
        parent::__construct(); // Initialize DB connection
    }

    // Helper to normalize single ID or multiple IDs (array or comma-separated string)
    private function normalizeSubjectIds($subject_id)
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

    private function buildInClause($ids)
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        return ['sql' => $placeholders, 'params' => $ids];
    }

    private function getSubjectType($subject_id)
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
     * Fetch Course Outcome (CO) Attainment Analysis
     * Returns CO attainment percentages and suggestions based on threshold.
     */
    public function getCOAttainment($subject_id, $assessment_id = null, $component_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            // Choice-aware calculation for Theory: either/or choice questions (1-2, 3-4, 5-6)
            $where_clauses = ["c.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            }
            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }
            $where_sql = implode(" AND ", $where_clauses);

            // Fetch question-level mapped data per student and CO
            $qQuery = "
                SELECT
                    c.id as co_id, c.co_number, c.co_description,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM course_outcomes c
                JOIN question_co_mapping qcm ON qcm.co_id = c.id
                JOIN assessment_questions q ON qcm.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY c.co_number, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $coSummary = [];
            // Group subjective marks by [co_num][stu_id][assessment_number][mainNum]
            $studentCoSubMarks = [];

            foreach ($rawRows as $row) {
                $co_id = $row['co_id'];
                $co_num = $row['co_number'];
                $aNum = $row['assessment_number'];
                $stu_id = $row['stu_id'] ?? 0;
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!isset($coSummary[$co_num])) {
                    $coSummary[$co_num] = [
                        'co_id' => $co_id,
                        'co_number' => $co_num,
                        'co_description' => $row['co_description'],
                        'total_obtained' => 0,
                        'total_max' => 0
                    ];
                }

                if (!$stu_id) continue;

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($studentCoSubMarks[$co_num][$stu_id][$aNum][$mainNum])) {
                            $studentCoSubMarks[$co_num][$stu_id][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentCoSubMarks[$co_num][$stu_id][$aNum][$mainNum]['obtained'] += $obtained;
                        $studentCoSubMarks[$co_num][$stu_id][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        // Unnumbered question fallback
                        $coSummary[$co_num]['total_obtained'] += $obtained;
                        $coSummary[$co_num]['total_max'] += $qMarks;
                    }
                } else {
                    // Objective, Assignment etc.
                    $coSummary[$co_num]['total_obtained'] += $obtained;
                    $coSummary[$co_num]['total_max'] += $qMarks;
                }
            }

            // Resolve choice pairs per CO, per student, per assessment
            foreach ($studentCoSubMarks as $co_num => $students) {
                foreach ($students as $stu_id => $assessments) {
                    foreach ($assessments as $aNum => $mainQuestions) {
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

                            $coSummary[$co_num]['total_obtained'] += $pairObtained;
                            $coSummary[$co_num]['total_max'] += $pairMax;
                        }
                    }
                }
            }

            $results = [];
            foreach ($coSummary as $co_num => $info) {
                $percentage = ($info['total_max'] > 0) ? round(($info['total_obtained'] / $info['total_max']) * 100, 2) : 0;
                $results[] = [
                    'co_id' => $info['co_id'],
                    'co_number' => $info['co_number'],
                    'co_label' => 'CO' . $info['co_number'],
                    'co_description' => $info['co_description'],
                    'co_attainment_percentage' => $percentage
                ];
            }
        } else {
            // Standard calculation for Lab, DTI, etc. Group by co_number to merge across batches
            $query = "
                SELECT
                    MIN(c.id) as co_id, c.co_number, CONCAT('CO', c.co_number) AS co_label, MIN(c.co_description) as co_description,
                    COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks), 0)) * 100, 2), 0) AS co_attainment_percentage
                FROM course_outcomes c
                LEFT JOIN question_co_mapping qcm ON qcm.co_id = c.id
                LEFT JOIN assessment_questions q ON qcm.question_id = q.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                LEFT JOIN assessment_components ac ON q.component_id = ac.id
                LEFT JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE c.sub_id IN ({$in['sql']})
            ";
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $query .= " AND i.assessment_number = ?";
                $params[] = $assessment_id;
            }

            if ($component_id) {
                $query .= " AND q.component_id = ?";
                $params[] = $component_id;
            }

            $query .= ' GROUP BY c.co_number ORDER BY c.co_number';
            $results = $this->fetchAssoc($query, $params);
        }

        // Generate suggestions
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['co_attainment_percentage'] < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "Attainment for {$row['co_label']} ({$row['co_attainment_percentage']}%) is below the target (" . self::TARGET_ATTAINMENT_THRESHOLD . "%). Consider reviewing teaching methods or assessment difficulty for this outcome: '{$row['co_description']}'.";
            }
        }

        // Add overall feedback if no COs meet the target significantly
        $avg_attainment = !empty($results) ? array_sum(array_column($results, 'co_attainment_percentage')) / count($results) : 0;
        if (!empty($results) && $avg_attainment < self::TARGET_ATTAINMENT_THRESHOLD + 5) { // Example threshold for overall concern
            $suggestions[] = "Overall CO attainment seems low. A review of the course delivery and assessment strategy might be beneficial.";
        } elseif (empty($results)) {
            $suggestions[] = "No CO attainment data found for the selected criteria. Ensure questions are mapped to COs and marks are entered.";
        } else {
            $suggestions[] = "CO attainment analysis complete. Review individual COs for specific insights.";
        }


        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }


    /**
     * Fetch Program Outcome (PO) Attainment Analysis
     * Calculates PO attainment based on the attainment of mapped COs.
     */
    public function getPOAttainment($subject_id, $assessment_id = null)
    {
        // 1. Get CO Attainment data (internal call, simplified)
        $co_attainment_data_json = $this->getCOAttainment($subject_id, $assessment_id);
        $co_attainment_data = json_decode($co_attainment_data_json, true);

        if (empty($co_attainment_data['data'])) {
            return json_encode(['data' => [], 'suggestions' => ["Cannot calculate PO attainment: No CO attainment data available."]]);
        }

        // Create a map of co_id and co_number to attainment percentage
        $co_attainment_map = [];
        $co_num_attainment_map = [];
        foreach ($co_attainment_data['data'] as $co_data) {
            $co_attainment_map[$co_data['co_id']] = $co_data['co_attainment_percentage'];
            $co_num_attainment_map[$co_data['co_number']] = $co_data['co_attainment_percentage'];
        }

        // 2. Get CO-PO Mappings for the subject (requires joining through subjects/classes to get regulation/specid)
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
            -- AND p.regulation = cl.regulation -- Assuming regulation is in classes table
            -- AND p.specid = cl.specid       -- Assuming specid is in classes table
            GROUP BY p.id, co.co_number
            ORDER BY p.orderid, p.code, co.co_number
        ";

        $params_mapping = $in['params'];
        $mappings = $this->fetchAssoc($query_mapping, $params_mapping);

        if (empty($mappings)) {
            return json_encode(['data' => [], 'suggestions' => ["Cannot calculate PO attainment: No CO-PO mapping found for this subject."]]);
        }

        // 3. Calculate PO Attainment
        $po_attainment = [];
        $po_details = []; // Store PO details to avoid repetition

        foreach ($mappings as $map) {
            $po_id = $map['po_id'];
            $co_id = $map['co_id'];
            $co_num = $map['co_number'];
            $weightage = $map['weightage'] ?? 1.0; // Default weightage if null

            if (!isset($po_details[$po_id])) {
                $po_details[$po_id] = ['label' => $map['po_label'], 'description' => $map['po_description']];
                $po_attainment[$po_id] = ['total_weighted_attainment' => 0, 'total_weightage' => 0, 'contributing_co_count' => 0];
            }

            // Only include COs for which we have attainment data (check co_id or co_number)
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

        // Finalize PO attainment percentages
        $po_results = [];
        $suggestions = [];
        foreach ($po_attainment as $po_id => $data) {
            $final_attainment = ($data['total_weightage'] > 0)
                ? round($data['total_weighted_attainment'] / $data['total_weightage'], 2)
                : 0; // Avoid division by zero

            $po_results[] = [
                'po_label' => $po_details[$po_id]['label'],
                'po_description' => $po_details[$po_id]['description'],
                'po_attainment_percentage' => $final_attainment,
                'contributing_co_count' => $data['contributing_co_count']
            ];

            // Add suggestions for POs
            if ($final_attainment < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "Attainment for {$po_details[$po_id]['label']} ({$final_attainment}%) is below the target (" . self::TARGET_ATTAINMENT_THRESHOLD . "%). Review the attainment of contributing COs for insights. PO Description: '{$po_details[$po_id]['description']}'.";
            }
        }

        if (empty($po_results)) {
            $suggestions[] = "PO attainment calculated, but some contributing COs might lack assessment data.";
        } elseif (!empty($po_results)) {
            $suggestions[] = "PO attainment analysis complete. Review individual POs and their contributing COs.";
        } else {
            $suggestions[] = "Could not calculate PO attainment. Check CO attainment and CO-PO mappings.";
        }


        return json_encode(['data' => $po_results, 'suggestions' => $suggestions]);
    }


    /**
     * Fetch Bloom's Taxonomy Performance Analysis
     */
    public function getBloomsPerformance($subject_id, $assessment_id = null, $component_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            // Choice-aware calculation for Theory: either/or choice questions (1-2, 3-4, 5-6)
            $where_clauses = ["i.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            }
            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }
            $where_sql = implode(" AND ", $where_clauses);

            $qQuery = "
                SELECT
                    b.id as blooms_id, b.blooms_level, b.blooms_label,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM blooms_levels b
                JOIN assessment_questions q ON q.blooms_level_id = b.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY b.id, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $bloomsSummary = [];
            // Group subjective marks by [bId][stu_id][assessment_number][mainNum]
            $studentBloomsSubMarks = [];

            foreach ($rawRows as $row) {
                $bId = $row['blooms_id'];
                $bLevel = $row['blooms_level'];
                $bLabel = $row['blooms_label'];
                $aNum = $row['assessment_number'];
                $stu_id = $row['stu_id'] ?? 0;
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!isset($bloomsSummary[$bId])) {
                    $bloomsSummary[$bId] = [
                        'blooms_level' => $bLevel,
                        'blooms_label' => $bLabel,
                        'total_obtained' => 0,
                        'total_max' => 0
                    ];
                }

                if (!$stu_id) continue;

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum])) {
                            $studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum]['obtained'] += $obtained;
                        $studentBloomsSubMarks[$bId][$stu_id][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        // Unnumbered question fallback
                        $bloomsSummary[$bId]['total_obtained'] += $obtained;
                        $bloomsSummary[$bId]['total_max'] += $qMarks;
                    }
                } else {
                    $bloomsSummary[$bId]['total_obtained'] += $obtained;
                    $bloomsSummary[$bId]['total_max'] += $qMarks;
                }
            }

            foreach ($studentBloomsSubMarks as $bId => $students) {
                foreach ($students as $stu_id => $assessments) {
                    foreach ($assessments as $aNum => $mainQuestions) {
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

                            $bloomsSummary[$bId]['total_obtained'] += $pairObtained;
                            $bloomsSummary[$bId]['total_max'] += $pairMax;
                        }
                    }
                }
            }

            $results = [];
            foreach ($bloomsSummary as $bId => $info) {
                $percentage = ($info['total_max'] > 0) ? round(($info['total_obtained'] / $info['total_max']) * 100, 2) : 0;
                $results[] = [
                    'blooms_level' => $info['blooms_level'],
                    'blooms_label' => $info['blooms_label'],
                    'attainment_percentage' => $percentage
                ];
            }
        } else {
            // Standard calculation for Lab, DTI, etc.
            $query = "
                SELECT
                    b.blooms_level, b.blooms_label,
                    COALESCE(ROUND((SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN m.marks_obtained ELSE 0 END) /
                             NULLIF(SUM(CASE WHEN i.sub_id IN ({$in['sql']}) THEN q.marks ELSE 0 END), 0)) * 100, 2), 0) AS attainment_percentage
                FROM blooms_levels b
                LEFT JOIN assessment_questions q ON q.blooms_level_id = b.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                LEFT JOIN assessment_components ac ON q.component_id = ac.id
                LEFT JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
            ";

            // Note: in the SELECT clause we have 2 occurrences of IN ({$in['sql']}) and 1 in the WHERE clause
            $params = array_merge($in['params'], $in['params'], $in['params']);
            $where_clauses = [];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            }

            if ($component_id) {
                $where_clauses[] = "q.component_id = ?";
                $params[] = $component_id;
            }

            if (!empty($where_clauses)) {
                $query .= " AND " . implode(" AND ", $where_clauses);
            }

            $query .= ' GROUP BY b.id, b.blooms_level ORDER BY b.id';
            $results = $this->fetchAssoc($query, $params);
        }

        // Generate suggestions
        $suggestions = [];
        $higher_order_low = false;
        $lower_order_low = false;
        $threshold = self::TARGET_ATTAINMENT_THRESHOLD;

        foreach ($results as $row) {
            if ($row['attainment_percentage'] < $threshold) {
                $suggestions[] = "Performance in Bloom's level '{$row['blooms_label']}' ({$row['attainment_percentage']}%) is below target ($threshold%). Focus on activities strengthening skills at this cognitive level.";
                if (in_array($row['blooms_level'], ['Analyze', 'Evaluate', 'Create'])) { // Assuming these are higher levels
                    $higher_order_low = true;
                }
                if (in_array($row['blooms_level'], ['Remember', 'Understand'])) { // Assuming these are lower levels
                    $lower_order_low = true;
                }
            }
        }

        if ($higher_order_low) {
            $suggestions[] = "Consider incorporating more activities that challenge higher-order thinking skills (Analysis, Evaluation, Creation).";
        }
        if ($lower_order_low) {
            $suggestions[] = "Low performance in foundational levels (Remembering, Understanding) might impact higher-level skills. Ensure fundamental concepts are clear.";
        }
        if (empty($results)) {
            $suggestions[] = "No Bloom's Taxonomy performance data found. Ensure questions are mapped to Bloom's levels and marks are entered.";
        }


        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }


    public function getAssessmentPerformance($subject_id, $assessment_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);
        $isLab = ($sub_type === 'lab' || $sub_type === 'dti');

        $labelExpr = $isLab ? "'Lab CIA'" : "CONCAT('CIA - ', i.assessment_number)";

        $compFilter = "";
        if ($isLab) {
            // For Lab, only "Internal Exam" is the formal assessment (Day-to-Day is continuous evaluation, tracked under Component Performance)
            $compFilter = " AND ac.component_type = 'Internal Exam'";
        }

        if ($sub_type === 'theory') {
            // Choice-aware calculation for Theory: either/or choice questions (1-2, 3-4, 5-6)
            $where_clauses = ["i.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            if ($assessment_id !== null && $assessment_id !== 'all' && filter_var($assessment_id, FILTER_VALIDATE_INT) !== false) {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = (int)$assessment_id;
            }
            $where_sql = implode(" AND ", $where_clauses);

            $qQuery = "
                SELECT
                    i.assessment_number,
                    CONCAT('CIA - ', i.assessment_number) AS assessment_label,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    ac.component_type,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM assessment_questions q
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY i.assessment_number, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $assessSummary = [];
            // Group subjective marks by [aNum][stu_id][mainNum]
            $studentAssessSubMarks = [];

            foreach ($rawRows as $row) {
                $aNum = $row['assessment_number'];
                $aLabel = $row['assessment_label'];
                $stu_id = $row['stu_id'] ?? 0;
                $comp = strtolower($row['component_type']);
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!isset($assessSummary[$aNum])) {
                    $assessSummary[$aNum] = [
                        'assessment_number' => $aNum,
                        'assessment_label' => $aLabel,
                        'total_obtained' => 0,
                        'total_max' => 0
                    ];
                }

                if (!$stu_id) continue;

                if ($comp === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($studentAssessSubMarks[$aNum][$stu_id][$mainNum])) {
                            $studentAssessSubMarks[$aNum][$stu_id][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentAssessSubMarks[$aNum][$stu_id][$mainNum]['obtained'] += $obtained;
                        $studentAssessSubMarks[$aNum][$stu_id][$mainNum]['max'] += $qMarks;
                    } else {
                        // Unnumbered question fallback
                        $assessSummary[$aNum]['total_obtained'] += $obtained;
                        $assessSummary[$aNum]['total_max'] += $qMarks;
                    }
                } else {
                    $assessSummary[$aNum]['total_obtained'] += $obtained;
                    $assessSummary[$aNum]['total_max'] += $qMarks;
                }
            }

            foreach ($studentAssessSubMarks as $aNum => $students) {
                foreach ($students as $stu_id => $mainQuestions) {
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

                        $assessSummary[$aNum]['total_obtained'] += $pairObtained;
                        $assessSummary[$aNum]['total_max'] += $pairMax;
                    }
                }
            }

            $results = [];
            $allObtained = 0;
            $allMax = 0;
            foreach ($assessSummary as $aNum => $info) {
                $percentage = ($info['total_max'] > 0) ? round(($info['total_obtained'] / $info['total_max']) * 100, 2) : 0;
                $results[] = [
                    'assessment_number' => $aNum,
                    'assessment_label' => $info['assessment_label'],
                    'attainment_percentage' => $percentage
                ];
                $allObtained += $info['total_obtained'];
                $allMax += $info['total_max'];
            }

            // Append Overall Average bar for comparative view if multiple assessments
            if (count($results) > 1) {
                $overallAttainment = ($allMax > 0) ? round(($allObtained / $allMax) * 100, 2) : 0;
                $results[] = [
                    'assessment_number' => 'all',
                    'assessment_label' => 'Overall Average',
                    'attainment_percentage' => $overallAttainment
                ];
            }
        } else {
            // Standard calculation for Lab, DTI, etc.
            $query = "
                SELECT
                    i.assessment_number,
                    {$labelExpr} AS assessment_label,
                    COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks),0)) * 100, 2), 0) AS attainment_percentage
                FROM student_marks m
                JOIN assessment_questions q ON m.question_id = q.id
                JOIN assessment_components ac ON q.component_id = ac.id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']}) {$compFilter}
            ";
            $params = $in['params'];

            if ($assessment_id !== null && $assessment_id !== 'all' && filter_var($assessment_id, FILTER_VALIDATE_INT) !== false) {
                $query .= " AND i.assessment_number = ?";
                $params[] = (int)$assessment_id;
            }

            $query .= " GROUP BY i.assessment_number ORDER BY i.assessment_number";
            $results = $this->fetchAssoc($query, $params);

            if (count($results) > 1) {
                $overallQuery = "
                    SELECT COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks),0)) * 100, 2), 0) AS overall_attainment
                    FROM student_marks m
                    JOIN assessment_questions q ON m.question_id = q.id
                    JOIN assessment_components ac ON q.component_id = ac.id
                    JOIN internal_assessments i ON ac.assessment_id = i.id
                    WHERE i.sub_id IN ({$in['sql']}) {$compFilter}
                ";
                $overallRes = $this->fetchAssoc($overallQuery, $in['params']);
                if (!empty($overallRes)) {
                    $results[] = [
                        'assessment_number' => 'all',
                        'assessment_label' => 'Overall Average',
                        'attainment_percentage' => $overallRes[0]['overall_attainment']
                    ];
                }
            }
        }

        // Generate suggestions
        $suggestions = [];
        $numericResults = array_filter($results, function($r) { return $r['assessment_number'] !== 'all'; });
        $numericResults = array_values($numericResults);

        if (count($numericResults) > 1) {
            $first = $numericResults[0]['attainment_percentage'];
            $last = end($numericResults)['attainment_percentage'];
            if ($last < $first && $last < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "Comparison shows performance declined from CIA-{$numericResults[0]['assessment_number']} ({$first}%) to CIA-{$numericResults[count($numericResults) - 1]['assessment_number']} ({$last}%).";
            } elseif ($last < self::TARGET_ATTAINMENT_THRESHOLD && $first < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "Performance across assessments is consistently below the target (" . self::TARGET_ATTAINMENT_THRESHOLD . "%).";
            } else {
                $suggestions[] = "Performance across assessments noted.";
            }
        } elseif (count($numericResults) == 1) {
            $label = $numericResults[0]['assessment_label'];
            if ($numericResults[0]['attainment_percentage'] < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "Performance in {$label} ({$numericResults[0]['attainment_percentage']}%) is below the target (" . self::TARGET_ATTAINMENT_THRESHOLD . "%).";
            } else {
                $suggestions[] = "Performance in {$label} meets the target.";
            }
        }

        if (empty($results)) {
            if ($isLab) {
                $suggestions[] = "No Internal Exam marks found. Note: Only the Internal Exam is considered as the formal assessment for Lab CIA (Day-to-Day lab evaluation is displayed in Component-wise Performance).";
            } else {
                $suggestions[] = "No assessment performance data found for the selected scope.";
            }
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions, 'sub_type' => $sub_type]);
    }

    /**
     * Fetch Component Performance Analysis (Subjective, Objective etc. within one or all assessments)
     */
    public function getComponentPerformance($subject_id, $assessment_id = null)
    {
        $sub_type = $this->getSubjectType($subject_id);
        $sub_ids = $this->normalizeSubjectIds($subject_id);
        $in = $this->buildInClause($sub_ids);

        if ($sub_type === 'theory') {
            // Choice-aware calculation for Theory: either/or choice questions (1-2, 3-4, 5-6)
            $where_clauses = ["i.sub_id IN ({$in['sql']})"];
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $where_clauses[] = "i.assessment_number = ?";
                $params[] = $assessment_id;
            }
            $where_sql = implode(" AND ", $where_clauses);

            $qQuery = "
                SELECT
                    ac.component_type,
                    i.assessment_number,
                    m.stu_id,
                    q.id as question_id,
                    q.question_label,
                    q.marks as q_marks,
                    COALESCE(m.marks_obtained, 0) as marks_obtained
                FROM assessment_components ac
                JOIN assessment_questions q ON ac.id = q.component_id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                LEFT JOIN student_marks m ON q.id = m.question_id
                WHERE {$where_sql}
                ORDER BY ac.component_type, m.stu_id, q.id
            ";
            $rawRows = $this->fetchAssoc($qQuery, $params);

            $compSummary = [];
            // Group subjective marks by [comp][stu_id][assessment_number][mainNum]
            $studentCompSubMarks = [];

            foreach ($rawRows as $row) {
                $comp = $row['component_type'];
                $compLower = strtolower($comp);
                $aNum = $row['assessment_number'];
                $stu_id = $row['stu_id'] ?? 0;
                $qLabel = $row['question_label'];
                $obtained = (float)$row['marks_obtained'];
                $qMarks = (float)$row['q_marks'];

                if (!isset($compSummary[$comp])) {
                    $compSummary[$comp] = [
                        'component_type' => $comp,
                        'total_obtained' => 0,
                        'total_max' => 0
                    ];
                }

                if (!$stu_id) continue;

                if ($compLower === 'subjective') {
                    $mainNum = (int)preg_replace('/[^0-9]/', '', $qLabel);
                    if ($mainNum > 0) {
                        if (!isset($studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum])) {
                            $studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum] = ['obtained' => 0, 'max' => 0];
                        }
                        $studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum]['obtained'] += $obtained;
                        $studentCompSubMarks[$comp][$stu_id][$aNum][$mainNum]['max'] += $qMarks;
                    } else {
                        // Unnumbered question fallback
                        $compSummary[$comp]['total_obtained'] += $obtained;
                        $compSummary[$comp]['total_max'] += $qMarks;
                    }
                } else {
                    $compSummary[$comp]['total_obtained'] += $obtained;
                    $compSummary[$comp]['total_max'] += $qMarks;
                }
            }

            foreach ($studentCompSubMarks as $comp => $students) {
                foreach ($students as $stu_id => $assessments) {
                    foreach ($assessments as $aNum => $mainQuestions) {
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

                            $compSummary[$comp]['total_obtained'] += $pairObtained;
                            $compSummary[$comp]['total_max'] += $pairMax;
                        }
                    }
                }
            }

            // Custom ordering: Day-to-Day, Internal Exam, Subjective, Objective, Assignment
            $orderMap = ['day-to-day' => 1, 'internal exam' => 2, 'subjective' => 3, 'objective' => 4, 'assignment' => 5];
            uksort($compSummary, function($a, $b) use ($orderMap) {
                $oa = $orderMap[strtolower($a)] ?? 99;
                $ob = $orderMap[strtolower($b)] ?? 99;
                return $oa <=> $ob;
            });

            $results = [];
            foreach ($compSummary as $comp => $info) {
                $percentage = ($info['total_max'] > 0) ? round(($info['total_obtained'] / $info['total_max']) * 100, 2) : 0;
                $results[] = [
                    'component_type' => $info['component_type'],
                    'attainment_percentage' => $percentage
                ];
            }
        } else {
            // Standard calculation for Lab, DTI, etc.
            $query = "
                SELECT
                    ac.component_type,
                    COALESCE(ROUND((SUM(m.marks_obtained) / NULLIF(SUM(q.marks),0)) * 100, 2), 0) AS attainment_percentage
                FROM assessment_components ac
                JOIN assessment_questions q ON ac.id = q.component_id
                JOIN student_marks m ON q.id = m.question_id
                JOIN internal_assessments i ON ac.assessment_id = i.id
                WHERE i.sub_id IN ({$in['sql']})
            ";
            $params = $in['params'];

            if ($assessment_id && $assessment_id !== 'all') {
                $query .= " AND i.assessment_number = ?";
                $params[] = $assessment_id;
            }

            $query .= " GROUP BY ac.component_type ORDER BY FIELD(ac.component_type, 'Day-to-Day', 'Internal Exam', 'Subjective', 'Objective', 'Assignment')";

            $results = $this->fetchAssoc($query, $params);
        }

        // Generate suggestions
        $suggestions = [];
        foreach ($results as $row) {
            if ($row['attainment_percentage'] < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "Performance in '{$row['component_type']}' components ({$row['attainment_percentage']}%) is below target (" . self::TARGET_ATTAINMENT_THRESHOLD . "%). This might indicate students struggle with this question format or the topics assessed via this component.";
            }
        }
        if (empty($results)) {
            $suggestions[] = "No component performance data found. Ensure questions, components, and marks are entered.";
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    /**
     * Fetch data for CO-PO Matrix/Table
     * Returns CO attainment linked with mapped POs and their weightages.
     */
    public function getCoPoMatrixData($subject_id, $assessment_id = null)
    {
        // 1. Get CO Attainment
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
            // Return empty if no CO data
            return json_encode(['data' => [], 'suggestions' => ['No CO attainment data available to build CO-PO Matrix.']]);
        }


        // 2. Get CO-PO Mappings
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
             -- AND p.regulation = cl.regulation -- Optional: Filter POs by class regulation/specid if needed
             -- AND p.specid = cl.specid
            GROUP BY co.co_number, p.id
            ORDER BY co.co_number, p.orderid, p.code
        ";
        $params_mapping = $in['params'];
        $mappings = $this->fetchAssoc($query_mapping, $params_mapping);

        // 3. Combine data
        $matrix_data = [];
        foreach ($mappings as $map) {
            $co_id = $map['co_id'];
            $co_num = $map['co_number'];
            $attainment = $co_map[$co_id] ?? ($co_num_map[$co_num] ?? 'N/A');
            $matrix_data[] = [
                'co_label' => $map['co_label'],
                'co_attainment' => $attainment, // Use N/A if CO attainment wasn't calculated
                'po_label' => $map['po_label'] ?? 'Not Mapped', // Handle COs not mapped to any PO
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

            // Get distinct student count
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
            }
            $stuRes = $this->fetchAssoc($stuCountQuery, $stuParams);
            $stuCount = $stuRes[0]['student_count'] ?? 0;

            if (!empty($coAttData['data'])) {
                foreach ($coAttData['data'] as $co) {
                    $results[] = [
                        'co_label' => $co['co_label'],
                        'avg_attainment' => $co['co_attainment_percentage'],
                        'student_count' => $stuCount
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
            }

            $query .= " GROUP BY c.co_number ORDER BY c.co_number";

            $results = $this->fetchAssoc($query, $params);
        }

        $suggestions = [];
        foreach ($results as $row) {
            if ($row['avg_attainment'] < self::TARGET_ATTAINMENT_THRESHOLD) {
                $suggestions[] = "CO {$row['co_label']} average ({$row['avg_attainment']}%) is below target. Consider remedial support.";
            }
        }

        return json_encode(['data' => $results, 'suggestions' => $suggestions]);
    }

    public function getQuestionDifficultyDiscrimination($subject_id, $assessment_id = null)
{
    $sub_ids = $this->normalizeSubjectIds($subject_id);
    $in = $this->buildInClause($sub_ids);

    $select_columns = "
        aq.id AS question_id,
        aq.question_label AS question_text,
        ac.component_type,
        ac.sequence_number,
        i.assessment_number,
        ROUND(AVG(sm.marks_obtained / aq.marks) * 100, 2) AS avg_difficulty,
        STDDEV_POP(sm.marks_obtained) AS std_dev
    ";
    $query = "
        SELECT $select_columns FROM assessment_questions aq
        JOIN student_marks sm ON aq.id = sm.question_id
        JOIN assessment_components ac ON aq.component_id = ac.id
        JOIN internal_assessments i ON ac.assessment_id = i.id
        WHERE i.sub_id IN ({$in['sql']})
    ";

    $params = $in['params'];
    if ($assessment_id && $assessment_id !== 'all') {
        $query .= " AND i.assessment_number = ?";
        $params[] = $assessment_id;
    }

    $query .= "
        GROUP BY aq.id, ac.component_type, ac.sequence_number
    ";

    if ($assessment_id && $assessment_id !== 'all') {
        $query .= "
            ORDER BY
                CASE ac.component_type
                    WHEN 'subjective' THEN 1
                    WHEN 'objective' THEN 2
                    WHEN 'assignment' THEN 3
                    ELSE 4
                END,
                ac.sequence_number,
                aq.id
        ";
    } else {
        $query = "SELECT $select_columns FROM assessment_questions aq
        JOIN student_marks sm ON aq.id = sm.question_id
        JOIN assessment_components ac ON aq.component_id = ac.id
        JOIN internal_assessments i ON ac.assessment_id = i.id
        WHERE i.sub_id IN ({$in['sql']})
        GROUP BY aq.id, ac.component_type, ac.sequence_number, i.assessment_number
        ORDER BY i.assessment_number,
                 aq.id";
    }

    $data = $this->fetchAssoc($query, $params);
    $suggestions = [];

    foreach ($data as $q) {
        $component_display = ucfirst($q['component_type']);
        if($q['assessment_number']){
            $component_display = $q['assessment_number'].".".$component_display;
        }
        if ($q['avg_difficulty'] < 30) {
            $suggestions[] = "CIA-'{$component_display} - {$q['question_text']}' appears too difficult. Consider reviewing.";
        } elseif ($q['avg_difficulty'] > 85) {
            $suggestions[] = "CIA-'{$component_display} - {$q['question_text']}' is too easy. Consider increasing complexity.";
        }
    }

    return json_encode(['data' => $data, 'suggestions' => $suggestions]);
}

    /**
     * Helper function to execute query and fetch associative array
     * Includes basic error handling.
     */
    private function fetchAssoc($query, $params)
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare($query);

        if ($stmt === false) {
            // Log error properly if logs class is available
            // error_log("SQL Prepare Error: " . $conn->error);
            return []; // Return empty array on error
        }

        if (!empty($params)) {
            // Dynamically determine types (assuming mostly integers 'i' or strings 's')
            // For simplicity, assuming 'i' for IDs/numbers, adjust if needed
            $types = str_repeat('i', count($params)); // Adjust if string parameters are common
            $stmt->bind_param($types, ...$params);
        }

        $stmt->execute();
        $result = $stmt->get_result();

        if (!$result) {
            // Log error
            // error_log("SQL Execution Error: " . $stmt->error);
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


// ================= AJAX Request Handler =================
if (isset($_GET['action']) && !empty($_GET['sub_id'])) {
    $analysisObj = new COAnalysis();

    // Sanitize inputs (support single integer or comma-separated integers, e.g. "101,102")
    $raw_sub_id = trim($_GET['sub_id']);
    if (!preg_match('/^[0-9]+(,[0-9]+)*$/', $raw_sub_id)) {
        echo json_encode(["error" => "Invalid Subject ID"]);
        exit;
    }
    $sub_id = (strpos($raw_sub_id, ',') !== false) ? $raw_sub_id : (int)$raw_sub_id;

    // Handle 'all' assessment case explicitly
    $assessment_number_raw = $_GET['assessment_number'] ?? null;
    $assessment_number = ($assessment_number_raw === 'all' || $assessment_number_raw === null)
        ? null // Use null in backend logic for "all" or unspecified
        : filter_var($assessment_number_raw, FILTER_VALIDATE_INT); // Validate if specific number

    // Check assessment number validity only if it's not null (i.e., not 'all')
    if ($assessment_number_raw !== null && $assessment_number_raw !== 'all' && ($assessment_number === false || $assessment_number <= 0)) {
        echo json_encode(["error" => "Invalid Assessment Number"]);
        exit;
    }


    header('Content-Type: application/json'); // Ensure correct content type

    switch ($_GET['action']) {
        case 'co_attainment':
            echo $analysisObj->getCOAttainment($sub_id, $assessment_number);
            break;
        case 'po_attainment': // New endpoint
            echo $analysisObj->getPOAttainment($sub_id, $assessment_number);
            break;
        case 'blooms_performance':
            echo $analysisObj->getBloomsPerformance($sub_id, $assessment_number);
            break;
        case 'assessment_performance':
            echo $analysisObj->getAssessmentPerformance($sub_id, $assessment_number);
            break;
        case 'component_performance':
            echo $analysisObj->getComponentPerformance($sub_id, $assessment_number);
            break;
        case 'co_po_matrix': // New endpoint for matrix data
            echo $analysisObj->getCoPoMatrixData($sub_id, $assessment_number);
            break;
        case 'co_spread':
            echo $analysisObj->getCOVisualSummary($sub_id, $assessment_number);
            break;
        case 'question_difficulty':
            echo $analysisObj->getQuestionDifficultyDiscrimination($sub_id, $assessment_number);
            break;
        default:
            echo json_encode(["error" => "Invalid action specified"]);
            break;
    }
    exit; // Stop script execution after handling AJAX request
}
