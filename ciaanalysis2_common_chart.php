        <div class="card">
            <div class="card-header bg-secondary text-white">
                <h4>Analysis for: <?php echo htmlspecialchars($subject_details_header); ?></h4>
                <h6>Assessment Scope:
                    <?php
                    $is_lab_scope = (isset($selected_sub_type) && ($selected_sub_type === 'lab' || $selected_sub_type === 'dti'));
                    if ($is_lab_scope) {
                        echo "Lab CIA";
                    } elseif ($selected_assessment_number === 'all') {
                        echo "Overall CIA";
                    } elseif (is_numeric($selected_assessment_number)) {
                        echo "CIA - " . htmlspecialchars($selected_assessment_number);
                    } else {
                        echo "Not Selected";
                    }
                    ?>
                </h6>
            </div>
            <div class="card-body">

                <ul class="nav nav-tabs mb-3" id="analysisTab" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview-tab-pane" type="button" role="tab" aria-controls="overview-tab-pane" aria-selected="true">Overview</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="outcome-tab" data-bs-toggle="tab" data-bs-target="#outcome-tab-pane" type="button" role="tab" aria-controls="outcome-tab-pane" aria-selected="false">Outcome Analysis (CO/PO)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="blooms-tab" data-bs-toggle="tab" data-bs-target="#blooms-tab-pane" type="button" role="tab" aria-controls="blooms-tab-pane" aria-selected="false">Cognitive Level (Bloom's)</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="copo-matrix-tab" data-bs-toggle="tab" data-bs-target="#copo-matrix-tab-pane" type="button" role="tab" aria-controls="copo-matrix-tab-pane" aria-selected="false">CO-PO Matrix</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="advanced-tab" data-bs-toggle="tab" data-bs-target="#advanced-tab-pane" type="button" role="tab" aria-controls="advanced-tab-pane" aria-selected="false">Other Insights</button>
                    </li>
                </ul>

                <div class="tab-content" id="analysisTabContent">

                    <div class="tab-pane fade show active" id="overview-tab-pane" role="tabpanel" aria-labelledby="overview-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Assessment Performance Snapshot</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Displays the overall student performance percentage for the selected assessment scope (e.g., a specific CIA or Overall).<br>
                                            <i>Benefit:</i> Provides a clear view of performance on individual assessments or allows comparison when 'Overall CIA' is selected.
                                        </p>
                                        <div id="assessmentChartContainer" class="chart-container"><canvas id="assessmentChart"></canvas></div>
                                        <div id="assessmentStatWidget" style="display:none;"></div>
                                        <div id="assessmentChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Component-wise Performance</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Displays average attainment percentage across evaluation components (Subjective, Objective, Assignment for Theory; Day-to-Day vs. Internal Exam for Lab).<br>
                                            <i>Benefit:</i> Pinpoints format-specific strengths/weaknesses and compares continuous lab work against formal exam execution.
                                        </p>
                                        <div class="chart-container"><canvas id="componentChart"></canvas></div>
                                        <div id="componentChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="outcome-tab-pane" role="tabpanel" aria-labelledby="outcome-tab" tabindex="0">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">CO Attainment Analysis</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Displays the achievement level for each Course Outcome (CO). <br>
                                            <i>Benefit:</i> Pinpoints specific learning objectives where students are meeting or falling short of expectations.
                                        </p>
                                        <div class="chart-container"><canvas id="coChart"></canvas></div>
                                        <div id="coChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">PO Attainment Analysis</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Shows the calculated achievement level for each Program Outcome (PO) based on mapped COs. <br>
                                            <i>Benefit:</i> Provides insight into how the course contributes to broader program-level objectives (OBE).
                                        </p>
                                        <div class="chart-container"><canvas id="poChart"></canvas></div>
                                        <div id="poChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="blooms-tab-pane" role="tabpanel" aria-labelledby="blooms-tab" tabindex="0">
                        <div class="row justify-content-center">
                            <div class="col-md-8 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Bloom's Taxonomy Performance</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Analyzes student performance across different cognitive levels (Remember, Understand, Apply, etc.). <br>
                                            <i>Benefit:</i> Helps assess if students are developing both foundational knowledge and higher-order thinking skills.
                                        </p>
                                        <div class="chart-container"><canvas id="bloomsChart"></canvas></div>
                                        <div id="bloomsChartSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="copo-matrix-tab-pane" role="tabpanel" aria-labelledby="copo-matrix-tab" tabindex="0">
                        <div class="row justify-content-center">
                            <div class="col-md-10 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">CO-PO Attainment Matrix</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Lists each Course Outcome (CO), its attainment percentage, and the Program Outcomes (POs) it maps to. <br>
                                            <i>Benefit:</i> Provides a detailed view of how specific course learning objectives contribute to program outcomes and identifies potentially weak links.
                                        </p>
                                        <div id="coPoMatrixTableContainer" class="table-responsive mt-2">
                                            <p>Loading matrix data...</p>
                                        </div>
                                        <div id="coPoMatrixSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="advanced-tab-pane" role="tabpanel" aria-labelledby="advanced-tab" tabindex="0">
                        <div class="row">
                            <!-- Student-wise CO Spread -->
                            <div class="col-md-12 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Student-wise CO Distribution</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Visualizes how students are distributed in performance across each CO. <br>
                                            <i>Benefit:</i> Useful for identifying if averages are skewed by outliers or if many students are underachieving.
                                        </p>
                                        <div class="chart-container"><canvas id="coSpreadChart"></canvas></div>
                                        <div id="coSpreadSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Question Difficulty Analysis -->
                            <div class="col-md-12 mb-4">
                                <div class="card h-100 shadow-sm">
                                    <div class="card-body">
                                        <h5 class="card-title">Question Difficulty Overview</h5>
                                        <p class="card-text small text-muted">
                                            <i>Purpose:</i> Shows difficulty levels and variation for each question. <br>
                                            <i>Benefit:</i> Helps refine question setting and alignment with learning outcomes.
                                        </p>
                                        <div id="questionDifficultyTable" class="table-responsive mt-2">
                                            <p>Loading difficulty data...</p>
                                        </div>
                                        <div id="questionDiffSuggestions" class="suggestions mt-2 alert alert-info small">Loading suggestions...</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card-footer text-muted small">
                <?php
                require_once __DIR__ . '/facciaanalysis2.class.php';
                $coAnalysisFooter = new COAnalysis();
                $targetThreshFooter = !empty($selected_sub_id) ? $coAnalysisFooter->getTargetAttainmentThreshold($selected_sub_id) : 60.0;
                ?>
                Analysis generated on: <?php echo date('Y-m-d H:i:s'); ?>. Target Attainment Threshold set at <?php echo rtrim(rtrim(number_format($targetThreshFooter, 2), '0'), '.'); ?>% for suggestions.
                <?php
                // Check if ANY data was found for the subject - basic check using one endpoint
                //$facanalysisObj = new COAnalysis(); // Already instantiated
                //$resJson = $facanalysisObj->getAssessmentPerformance($selected_sub_id); // Fetch assessment data
                //$res = json_decode($resJson, true);
                //if (empty($res['data'])) {
                //    echo '<br><strong>Note: No assessment mark data found for this subject. Analysis may be incomplete. Please ensure detailed CIA marks are entered.</strong>';
                // }
                // This check is now less reliable due to AJAX loading, maybe remove or adapt
                ?>
            </div>
            <div class="card-footer">
                <p class="text-muted">Click on each section to view the formula and chart description.</p>
                <!-- Include MathJax -->
                 <script defer src="https://cdn.jsdelivr.net/npm/mathjax@4/tex-mml-chtml.js"></script>
                <!--<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>-->

                <div class="mb-3">
                    <button class="btn btn-sm btn-primary me-2" onclick="expandAll()">&#x25BC;</button>
                    <button class="btn btn-sm btn-secondary" onclick="collapseAll()">&#x25B2;</button>
                </div>

                <?php
                require_once("ciaanalysis2_common.php");
                echo renderAccordion();
                echo renderExpandCollapseScripts();
                ?>
            </div>
        </div>
