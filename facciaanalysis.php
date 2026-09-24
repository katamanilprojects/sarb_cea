<?php
session_start();
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$faculty_id = $_SESSION['facid'];
$facultySubjects = $facultyObj->getSubjectsByFacultyId($faculty_id);
$selected_sub_id = null;
$selected_assessment_number = null;

// Helpers to detect multi-batch courses and clean base course details
if (!function_exists('isMultiBatchCourse')) {
    function isMultiBatchCourse($subs) {
        if (count($subs) <= 1) return false;

        $sub_names = array_map('trim', array_column($subs, 'sub_fullname'));
        $prefix = $sub_names[0];
        foreach ($sub_names as $nm) {
            while (stripos($nm, $prefix) !== 0) {
                $prefix = substr($prefix, 0, -1);
                if ($prefix === '') break;
            }
        }
        $common_len = strlen(trim(preg_replace('/[\s\-\–\(\[]+$/', '', $prefix)));

        // Batch / group keyword in name
        $pattern = '/[\s\-\–\(\[](?:batch|group|grp|section|sec)[\s\-\–]*[0-9a-z]+/i';
        $has_batch_keyword = false;
        foreach ($sub_names as $nm) {
            if (preg_match($pattern, $nm)) {
                $has_batch_keyword = true;
                break;
            }
        }

        // Matching shortnames
        $shorts = array_unique(array_filter(array_map(function($s) {
            return strtoupper(trim(preg_replace('/[\s\-\–\(\[]*(?:batch|group|grp|section|sec)[\s\-\–]*[0-9a-z]+[\s\)\–\]]*$/i', '', $s['sub_shortname'] ?? '')));
        }, $subs)));
        $same_shortname = (count($shorts) === 1 && !empty($shorts[0]));

        if ($has_batch_keyword && $common_len >= 3) return true;
        if ($common_len >= 5) return true;
        if ($same_shortname && $common_len >= 3) return true;

        return false;
    }
}

if (!function_exists('getCleanBaseCourseDetails')) {
    function getCleanBaseCourseDetails($subs) {
        if (empty($subs)) {
            return ['base_name' => '', 'base_code' => ''];
        }
        $sub_names = array_column($subs, 'sub_fullname');
        $sub_codes = array_column($subs, 'subcode');
        $first_name = trim($subs[0]['sub_fullname'] ?? '');
        $first_code = trim($subs[0]['subcode'] ?? '');

        // Strip batch/group/section suffix like " - GROUP-A", "(Group A)", "[GROUP-A]", " - Batch 1", etc.
        $clean_regex = '/[\s\-\–\(\[]*(?:batch|group|grp|section|sec)[\s\-\–]*[0-9a-z]+[\s\)\–\]]*$/i';
        $base_name = preg_replace($clean_regex, '', $first_name);
        $base_name = trim(preg_replace('/[\s\-\–\(\[]+$/', '', $base_name));

        if ($base_name === $first_name && count($subs) > 1) {
            $prefix = $sub_names[0];
            foreach ($sub_names as $nm) {
                while (stripos($nm, $prefix) !== 0) {
                    $prefix = substr($prefix, 0, -1);
                    if ($prefix === '') break;
                }
            }
            $cand = trim(preg_replace('/[\s\-\–\(\[]+$/', '', $prefix));
            if (strlen($cand) >= 3) {
                $base_name = $cand;
            }
        }
        if (empty($base_name)) {
            $base_name = $first_name;
        }

        // Clean base code - find common prefix across codes or strip trailing batch letter
        $code_prefix = trim($sub_codes[0] ?? '');
        foreach ($sub_codes as $cd) {
            $cd = trim($cd);
            while (stripos($cd, $code_prefix) !== 0) {
                $code_prefix = substr($code_prefix, 0, -1);
                if ($code_prefix === '') break;
            }
        }
        $base_code = trim(preg_replace('/[\s\-\–\(\[]+$/', '', $code_prefix));
        if (empty($base_code)) {
            $base_code = trim(preg_replace('/[\s\-\–]*[a-zA-Z]$/', '', $first_code));
        }
        if (empty($base_code)) {
            $base_code = $first_code;
        }

        return ['base_name' => $base_name, 'base_code' => $base_code];
    }
}

// Group faculty's assigned subjects by class_id and subject_sno
$groupedFacultySubjects = [];
$allFacultySubjectIds = [];
if (!empty($facultySubjects['data'])) {
    foreach ($facultySubjects['data'] as $sub) {
        $allFacultySubjectIds[] = (int)$sub['id'];
        $cls_id = $sub['class_id'] ?? 0;
        $sno_key = !empty($sub['subject_sno']) ? $sub['subject_sno'] : ('id_' . $sub['id']);
        $group_key = $cls_id . '_' . $sno_key;
        $groupedFacultySubjects[$group_key][] = $sub;
    }
}

// Handle subject selection
if (!empty($_POST['sub_id'])) {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
    }
    $post_sub_id = trim($_POST['sub_id']);
    if (preg_match('/^[0-9]+(,[0-9]+)*$/', $post_sub_id)) {
        $input_ids = array_map('intval', explode(',', $post_sub_id));
        $all_valid = true;
        foreach ($input_ids as $iid) {
            if (!in_array($iid, $allFacultySubjectIds, true)) {
                $all_valid = false;
                break;
            }
        }
        if ($all_valid) {
            $selected_sub_id = $post_sub_id;
        } else {
            $_SESSION["err"] = "Invalid subject selected.";
        }
    }

    if (!empty($_POST['assessment_number'])) {
        $selected_assessment_number = $_POST['assessment_number'];
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("facheader.php");

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Course CIA Analysis</div>
                <div class="card-body">
                    <form action="facciaanalysis.php" method="post" id="ciacourseform">
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required  onchange="document.getElementById('ciacourseform').submit();">
                                <option value="">Select Subject</option>
                                <?php
                                if (!empty($groupedFacultySubjects)) {
                                    foreach ($groupedFacultySubjects as $gKey => $subs) {
                                        $firstSub = $subs[0];
                                        $clsName = $firstSub['class_name'] ?? '';
                                        $acadYr = $firstSub['acad_year'] ?? '';

                                        if (count($subs) === 1 || !isMultiBatchCourse($subs)) {
                                            // Single subject or distinct course
                                            foreach ($subs as $subject) {
                                                $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($subject['id'], ENT_QUOTES, 'UTF-8') . "' $selected>";
                                                echo htmlspecialchars("{$subject['sub_fullname']} ({$subject['subcode']}) - {$clsName} - {$acadYr}", ENT_QUOTES, 'UTF-8');
                                                echo "</option>";
                                            }
                                        } else {
                                            // Multiple batches of the same course assigned to this faculty
                                            $combined_ids = implode(',', array_column($subs, 'id'));
                                            $base_details = getCleanBaseCourseDetails($subs);
                                            $base_name = $base_details['base_name'];
                                            $base_code = $base_details['base_code'];

                                            $is_combined_selected = (!empty($selected_sub_id) && $selected_sub_id === $combined_ids) ? 'selected' : '';
                                            echo "<option value='{$combined_ids}' $is_combined_selected style='font-weight: bold;'>";
                                            echo "★ " . htmlspecialchars("{$base_name} ({$base_code}) [All Batches Combined] - {$clsName} - {$acadYr}", ENT_QUOTES, 'UTF-8');
                                            echo "</option>";

                                            foreach ($subs as $bSub) {
                                                $bSelected = (!empty($selected_sub_id) && $selected_sub_id == $bSub['id']) ? 'selected' : '';
                                                echo "<option value='" . htmlspecialchars($bSub['id'], ENT_QUOTES, 'UTF-8') . "' $bSelected>";
                                                echo "&nbsp;&nbsp;&nbsp;&nbsp;↳ " . htmlspecialchars("{$bSub['sub_fullname']} ({$bSub['subcode']}) - {$clsName}", ENT_QUOTES, 'UTF-8');
                                                echo "</option>";
                                            }
                                        }
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        <br />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                    </form>
                </div>
            </div>

            <?php if (!empty($_SESSION["succ"])) {
                echo '<div class="alert alert-success">' . $_SESSION["succ"] . '</div>';
                unset($_SESSION["succ"]);
            } ?>
            <?php if (!empty($_SESSION["err"])) {
                echo '<div class="alert alert-danger">' . $_SESSION["err"] . '</div>';
                unset($_SESSION["err"]);
            } ?>
        </div>
    </div>

    <?php if (!empty($selected_sub_id)) : ?>
        <br>
        <div class="card">
            <div class="card-header">
                Select Assessment
            </div>
            <div class="card-body">
                <form action="facciaanalysis.php" method="post" id="assessmentForm">
                    <select name="assessment_number" id="assessment_number" class="form-select" required onchange="document.getElementById('assessmentForm').submit();">
                        <option value="">--Select Assessment --</option>
                        <option value="1" <?php if (!empty($selected_assessment_number) && $selected_assessment_number=="1"){ echo ' selected'; } ?>>1</option>
                        <option value="2" <?php if (!empty($selected_assessment_number) && $selected_assessment_number=="2"){ echo ' selected'; } ?>>2</option>
                        <option value="all" <?php if (!empty($selected_assessment_number) && $selected_assessment_number=="all"){ echo ' selected'; } ?>>Overall CIA</option>
                    </select>
                    <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>" />
                    <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>" />
                </form>
            </div>
        </div>
        <?php if (!empty($selected_assessment_number)) : ?>
            <?php
            if ($selected_assessment_number == "all") {
                $selected_assessment_number = null;
            }
            ?>
            <!-- Chart.js & jQuery -->
            <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels"></script>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

            <style>
                .chart-container {
                    width: 100%;
                    min-height: 350px;
                }
            </style>

            <br>
            <div class="card">
                <div class="card-header">
                    <?php
                    $selected_id_list = explode(',', $selected_sub_id);
                    if (count($selected_id_list) > 1) {
                        $matched_subs = array_values(array_filter($facultySubjects['data'], function($s) use ($selected_id_list) {
                            return in_array((string)$s['id'], $selected_id_list, true);
                        }));
                        if (!empty($matched_subs)) {
                            $base_details = getCleanBaseCourseDetails($matched_subs);
                            $className = $matched_subs[0]['class_name'] ?? '';
                            $acadYear = $matched_subs[0]['acad_year'] ?? '';
                            echo htmlspecialchars("{$base_details['base_name']} ({$base_details['base_code']}) [All Batches Combined] - {$className} - {$acadYear}", ENT_QUOTES, 'UTF-8');
                        }
                    } else {
                        foreach ($facultySubjects['data'] as $subject) {
                            if (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) {
                                echo htmlspecialchars($subject['sub_fullname'], ENT_QUOTES, 'UTF-8') . " (";
                                echo htmlspecialchars($subject['subcode'], ENT_QUOTES, 'UTF-8') . ") - ";
                                echo htmlspecialchars($subject['class_name'], ENT_QUOTES, 'UTF-8');
                            }
                        }
                    }
                    ?>
                </div>
                <div class="card-header"><?php echo $selected_assessment_number ? "Analysis of CIA - " . $selected_assessment_number : "Overall Course CIA Analysis"; ?></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card p-3">
                                <h5>CO Attainment Analysis</h5>
                                <div class="chart-container">
                                    <canvas id="coChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3">
                                <h5>Bloom's Taxonomy Performance</h5>
                                <div class="chart-container">
                                    <canvas id="bloomsChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <div class="card p-3">
                                <h5>Assessment Performance</h5>
                                <div class="chart-container">
                                    <canvas id="assessmentChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <div class="card p-3">
                                <h5>Component-wise Performance</h5>
                                <div class="chart-container">
                                    <canvas id="componentChart"></canvas>
                                </div>
                            </div>
                        </div>
                        <script>
                            function getRandomColors(count) {
                                let colors = [];
                                for (let i = 0; i < count; i++) {
                                    let r = Math.floor(Math.random() * 255);
                                    let g = Math.floor(Math.random() * 255);
                                    let b = Math.floor(Math.random() * 255);
                                    colors.push(`rgba(${r}, ${g}, ${b}, 0.6)`);
                                }
                                return colors;
                            }

                            function loadChart(endpoint, canvasId, labelKey, valueKey, chartLabel, isPercentage = false) {
                                $.getJSON(`facciaanalysis.class.php?action=${endpoint}&sub_id=<?php echo $selected_sub_id; ?>&assessment_number=<?php echo $selected_assessment_number; ?>`, function(data) {
                                    if (!data || data.length === 0) {
                                        console.error(`No data returned for ${endpoint}`);
                                        return;
                                    }

                                    let labels = data.map(item => item[labelKey]);
                                    let values = data.map(item => item[valueKey]);
                                    let colors = getRandomColors(labels.length);

                                    new Chart(document.getElementById(canvasId), {
                                        type: 'bar',
                                        data: {
                                            labels: labels,
                                            datasets: [{
                                                label: chartLabel,
                                                data: values,
                                                backgroundColor: colors,
                                                borderColor: colors.map(c => c.replace('0.6', '1')),
                                                borderWidth: 1
                                            }]
                                        },
                                        options: {
                                            responsive: true,
                                            plugins: {
                                                legend: {
                                                    display: true
                                                },
                                                datalabels: {
                                                    anchor: 'end',
                                                    align: 'top',
                                                    formatter: function(value) {
                                                        return isPercentage ? value + '%' : value; // Show percentage if applicable
                                                    },
                                                    font: {
                                                        weight: 'bold'
                                                    },
                                                    color: '#333'
                                                }
                                            },
                                            scales: {
                                                y: {
                                                    beginAtZero: true,
                                                    max: isPercentage ? 100 : undefined
                                                }
                                            }
                                        },
                                        plugins: [ChartDataLabels] // Enable Data Labels Plugin
                                    });
                                }).fail(function(jqXHR, textStatus, errorThrown) {
                                    console.error(`Error fetching ${endpoint}:`, textStatus, errorThrown);
                                });
                            }

                            $(document).ready(function() {
                                loadChart('co_attainment', 'coChart', 'co_label', 'co_attainment_percentage', 'CO Attainment (%)', true);
                                loadChart('blooms_performance', 'bloomsChart', 'blooms_label', 'attainment_percentage', 'Bloom\'s Attainment (%)', true);
                                loadChart('assessment_performance', 'assessmentChart', 'assessment_number', 'attainment_percentage', 'Avg. (%) Marks per Assessment', true);
                                loadChart('component_performance', 'componentChart', 'component_type', 'attainment_percentage', 'Component-wise Performance (%)', true);
                            });
                        </script>
                    </div>
                </div>
                <div class="card-footer">
                    <?php
                    require_once("facciaanalysis.class.php");
                    $facanalysisObj = new COAnalysis();
                    $res = $facanalysisObj->getAssessmentPerformance($selected_sub_id);
                    $res = json_decode($res);
                    if (!is_array($res) || count($res) <= 0) {
                        echo '<strong>Note: Please Add Student Detailed CIA Marks to get the Analysis</strong>';
                    }
                    ?>
                </div>
            <?php endif; ?>
            <br>
        <?php endif; ?>
            </div>

            <?php
            require_once("facfooter.php");
            ?>