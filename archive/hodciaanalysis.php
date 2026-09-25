<?php
session_start();
$page_title = "Edit";
require_once("faculty.class.php");
require_once("cia.class.php");

$facultyObj = new Faculty();
$ciaObj = new CIA();
$faculty_id = $_SESSION['facid'];
$selected_sub_id = null;
$selected_assessment_number = null;
require_once("hod.class.php");
$hodObj = new HOD();

$selected_cls_id = null;
$selected_sub_id = null;

if (!empty($_POST['cls_id'])) {
    $selected_cls_id = $_POST['cls_id'];
    $facultySubjects = $hodObj->getSubjectsByClassID($selected_cls_id);
}

$specializations = $hodObj->getSpecializationsByDepartment($_SESSION["dept_id"]);

$classes_options = '';

if (!empty($specializations)) {
    foreach ($specializations as $spec) {
        $classes_options .= '<optgroup label="' . htmlspecialchars($spec['spec_fullname']) . '">';
        $classes = $hodObj->getActiveClassesBySpecialization($spec['id']);
        if (!empty($classes)) {
            foreach ($classes as $class) {
                $classes_options .= '<option value="' . $class['id'] . '"';
                if ($class['id'] == $selected_cls_id) {
                    $classes_options .= ' selected';
                }
                $classes_options .= '>' . $class['classname'] . '</option>';
            }
        }
        $classes_options .= '</optgroup>';
    }
}
// Handle subject selection
if (!empty($_POST['sub_id'])) {
    if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
        unset($_SESSION['secretcode']);
    }
    $selected_sub_id = $_POST['sub_id'];

    if (!empty($_POST['assessment_number'])) {
        $selected_assessment_number = $_POST['assessment_number'];
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

require_once("hodheader.php");

?>

<div class="container">
    <br />
    <div class="row">
        <div class="col-sm-12">
            <div class="card">
                <div class="card-header">Course CIA Analysis</div>
                <div class="card-body">
                    <form action="hodciaanalysis.php" method="post" id="ciacourseform">
                        <div class="form-group">
                            <label for="cls_id">Class:</label>
                            <select name="cls_id" id="cls_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php echo $classes_options; ?>
                            </select>
                        </div>
                        <input type="hidden" name="an" value="ab" />
                        <br />
                        <div class="form-group">
                            <label for="sub_id">Subject:</label>
                            <select name="sub_id" id="sub_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php
                                if (!empty($facultySubjects['data'])) {
                                    foreach ($facultySubjects['data'] as $subject) {
                                        $selected = (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) ? 'selected' : '';
                                        echo "<option value='{$subject['id']}' $selected>{$subject['sub_fullname']} ({$subject['subcode']})</option>";
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
                <form action="hodciaanalysis.php" method="post" id="assessmentForm">
                    <select name="assessment_number" id="assessment_number" class="form-select" required onchange="document.getElementById('assessmentForm').submit();">
                        <option value="">--Select Assessment --</option>
                        <option value="1" <?php if (!empty($selected_assessment_number) && $selected_assessment_number == "1") {
                                                echo ' selected';
                                            } ?>>1</option>
                        <option value="2" <?php if (!empty($selected_assessment_number) && $selected_assessment_number == "2") {
                                                echo ' selected';
                                            } ?>>2</option>
                        <option value="all" <?php if (!empty($selected_assessment_number) && $selected_assessment_number == "all") {
                                                echo ' selected';
                                            } ?>>Overall CIA</option>
                    </select>
                    <input type="hidden" name="cls_id" value="<?php echo $selected_cls_id; ?>" />
                    <input type="hidden" name="sub_id" value="<?php echo $selected_sub_id; ?>" />
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
                    foreach ($facultySubjects['data'] as $subject) {
                        if (!empty($selected_sub_id) && $selected_sub_id == $subject['id']) {
                            echo "{$subject['sub_fullname']} ({$subject['subcode']}) - {$subject['class_name']}";
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
            <script>
                document.getElementById('cls_id').addEventListener('change', function() {
                    document.getElementById("ciacourseform").submit();
                });
                document.getElementById('sub_id').addEventListener('change', function() {
                    document.getElementById("ciacourseform").submit();
                });
            </script>
            <?php
            require_once("hodfooter.php");
            ?>