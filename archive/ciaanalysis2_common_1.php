<?php
function renderAccordionItem($id, $title, $formula) {
    return "
        <div class=\"accordion-item\">
            <h2 class=\"accordion-header\" id=\"heading{$id}\">
                <button class=\"accordion-button collapsed\" type=\"button\" data-bs-toggle=\"collapse\" data-bs-target=\"#collapse{$id}\" aria-expanded=\"false\" aria-controls=\"collapse{$id}\">
                    {$title}
                </button>
            </h2>
            <div id=\"collapse{$id}\" class=\"accordion-collapse collapse\" data-bs-parent=\"#formulaAccordion\">
                <div class=\"accordion-body\">
                    {$formula}
                </div>
            </div>
        </div>
    ";
}

function renderAccordion() {
    $items = [
        [1, "Assessment Performance Snapshot (AssessmentChart)", "<p>Attainment %:</p><p>$$ \\text{Attainment %} = \\left( \\frac{\\sum \\text{marks_obtained}}{\\sum \\text{total_question_marks}} \\right) \\times 100 $$</p>"],
        [2, "Component-wise Performance (ComponentChart)", "<p>Attainment %:</p><p>$$ \\text{Attainment %} = \\left( \\frac{\\sum \\text{marks_obtained}}{\\sum \\text{total_question_marks}} \\right) \\times 100 $$</p>"],
        [3, "CO Attainment Analysis (COChart)", "<p>CO Attainment %:</p><p>$$ \\text{CO Attainment %} = \\left( \\frac{\\sum \\text{marks_obtained}}{\\sum \\text{total_question_marks}} \\right) \\times 100 $$</p>"],
        [4, "PO Attainment Analysis (POChart)", "<p>PO Attainment %:</p><p>$$ \\text{PO Attainment %} = \\frac{\\sum (\\text{CO_Attainment%} \\times \\text{Weightage})}{\\sum \\text{Weightage}} $$</p>"],
        [5, "Bloom's Taxonomy Performance (BloomsChart)", "<p>Attainment %:</p><p>$$ \\text{Attainment %} = \\left( \\frac{\\sum \\text{marks_obtained}}{\\sum \\text{total_question_marks}} \\right) \\times 100 $$</p>"],
        [6, "Student-wise CO Distribution (COSpreadChart)", "<p>Avg Student-wise CO Distribution %:</p><p>$$ \\text{Avg CO Distribution %} = \\text{AVG}\\left( \\frac{\\text{student_marks_obtained}}{\\text{question_total_marks}} \\times 100 \\right) $$</p>"],
        [7, "Question Difficulty Overview (QuestionDifficultyTable)", <<<HTML
            <p><strong>Average Difficulty %:</strong></p>
            <p>$$ \\text{Avg Difficulty %} = \\text{AVG}\\left( \\frac{\\text{student_marks_obtained}}{\\text{question_total_marks}} \\times 100 \\right) $$</p>
            <p><strong>Standard Deviation (Population):</strong></p>
            <p>$$ \\sigma = \\sqrt{ \\frac{ \\sum (x_i - \\mu)^2 }{ N } } $$</p>
            <ul>
                <li><strong>\\( x_i \\)</strong> = Individual student's marks obtained for the question</li>
                <li><strong>\\( \\mu \\)</strong> = Average marks obtained for the question</li>
                <li><strong>\\( N \\)</strong> = Total number of students</li>
            </ul>
        HTML]
    ];

    $output = "<div class=\"accordion\" id=\"formulaAccordion\">";
    foreach ($items as $item) {
        $output .= renderAccordionItem($item[0], $item[1], $item[2]);
    }
    $output .= "</div>";
    return $output;
}

function renderExpandCollapseScripts() {
    return "
        <script>
            function expandAll() {
                document.querySelectorAll('.accordion-collapse').forEach(item => item.classList.add('show'));
            }
            function collapseAll() {
                document.querySelectorAll('.accordion-collapse').forEach(item => item.classList.remove('show'));
            }
        </script>
    ";
}

function renderAnalysisAssets() {
    return '
        <!-- MathJax -->
                <!-- Include MathJax -->
                 <!--<script defer src="https://cdn.jsdelivr.net/npm/mathjax@4/tex-mml-chtml.js"></script>-->
                <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>

        <!-- Chart.js & Plugins -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

        <!-- jQuery & Bootstrap -->
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

        <style>
            .chart-container {
                position: relative;
                width: 100%;
                min-height: 300px;
                padding-bottom: 20px;
            }
            .suggestions {
                font-size: 0.85rem;
                padding: 0.5rem 0.8rem;
            }
            #coPoMatrixTable {
                width: 100%;
                font-size: 0.9rem;
            }
            #coPoMatrixTable th,
            #coPoMatrixTable td {
                text-align: center;
                vertical-align: middle;
            }
            #coPoMatrixTable .low-attainment {
                background-color: #f8d7da;
                color: #842029;
                font-weight: bold;
            }
        </style>
    ';
}

function renderAnalysisScripts($selected_sub_id, $selected_assessment_number) {
    
    require_once __DIR__ . '/facciaanalysis2.class.php';
    $coAnalysis = new COAnalysis();
    $targetThreshold = $coAnalysis->getTargetAttainmentThreshold($selected_sub_id);
    $thresholdJson = json_encode($targetThreshold);
    $subIdJson = json_encode($selected_sub_id);
    $assessmentJson = json_encode($selected_assessment_number);

    $script = <<<'HTML'
    <script>
        const TARGET_THRESHOLD = __THRESHOLD__;
        const SUB_ID = __SUB_ID__;
        const ASSESSMENT_NUMBER = __ASSESSMENT__;

                // Function to generate random colors (improved)
                function generateDistinctColors(count) {
                    const colors = [];
                    const hueStep = 360 / count;
                    for (let i = 0; i < count; i++) {
                        // Use HSL for better distinctiveness - Fixed Saturation/Lightness
                        const hue = i * hueStep;
                        colors.push(`hsla(${hue}, 70%, 60%, 0.7)`); // Adjust S/L for desired look
                    }
                    return colors;
                }

                // Function to display suggestions
                function displaySuggestions(containerId, suggestions) {
                    const container = $(`#${containerId}`);
                    if (suggestions && suggestions.length > 0) {
                        let html = '<ul>';
                        suggestions.forEach(s => {
                            html += `<li>${s}</li>`;
                        });
                        html += '</ul>';
                        container.html(html).removeClass('alert-info').addClass('alert-warning'); // Use warning style for suggestions
                    } else {
                        container.html('No specific suggestions based on current data.').removeClass('alert-warning').addClass('alert-secondary');
                    }
                }

                // Chart Instances Holder
                let chartInstances = {};

                // Enhanced Chart Loading Function
                function loadChart(endpoint, canvasId, labelKey, valueKey, chartLabel, chartType = 'bar', isPercentage = true, suggestionsContainerId) {
                    // Destroy previous chart instance if exists
                    if (chartInstances[canvasId]) {
                        chartInstances[canvasId].destroy();
                    }

                    $.getJSON(`facciaanalysis2.class.php?action=${endpoint}&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            if (!response || !response.data || response.data.length === 0) {
                                console.warn(`No data returned for ${endpoint}`);
                                $(`#${canvasId}`).hide(); // Hide canvas if no data
                                $(`#${suggestionsContainerId}`).html('No data available for this chart.').addClass('alert-secondary');
                                return;
                            }
                            console.log(response);
                            $(`#${canvasId}`).show(); // Ensure canvas is visible

                            let labels = response.data.map(item => item[labelKey]);
                            let values = response.data.map(item => item[valueKey]);
                            let colors = generateDistinctColors(labels.length);

                            // Prepare dataset(s)
                            const datasets = [{
                                label: chartLabel,
                                data: values,
                                backgroundColor: colors,
                                borderColor: colors.map(c => c.replace('0.7', '1')), // Solid border
                                borderWidth: 1
                            }];

                            // Chart Configuration
                            const config = {
                                type: chartType,
                                data: {
                                    labels: labels,
                                    datasets: datasets
                                },
                                options: {
                                    responsive: true,
                                    maintainAspectRatio: false, // Allow chart to fill container height
                                    plugins: {
                                        legend: {
                                            display: true, // Keep legend for context
                                            position: 'top',
                                        },
                                        tooltip: {
                                            callbacks: {
                                                label: function(context) {
                                                    let label = context.dataset.label || '';
                                                    if (label) {
                                                        label += ': ';
                                                    }
                                                    if (context.parsed.y !== null) {
                                                        label += context.parsed.y + (isPercentage ? '%' : '');
                                                    }
                                                    // Add description on tooltip (example for CO chart)
                                                    if (endpoint === 'co_attainment' && response.data[context.dataIndex]?.co_description) {
                                                        label += `\n(${response.data[context.dataIndex].co_description})`;
                                                    } else if (endpoint === 'po_attainment' && response.data[context.dataIndex]?.po_description) {
                                                        label += `\n(${response.data[context.dataIndex].po_description})`;
                                                    }
                                                    return label;
                                                }
                                            }
                                        },
                                        // Datalabels Plugin Config (Optional - uncomment library include if used)
                                        /*
                                        datalabels: {
                                            anchor: 'end',
                                            align: 'top',
                                            formatter: (value) => (isPercentage ? value + '%' : value),
                                            font: { weight: 'bold' },
                                            color: '#444'
                                        }
                                        */
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: isPercentage ? 100 : undefined, // Set max to 100 for percentages
                                            ticks: {
                                                callback: function(value) {
                                                    return value + (isPercentage ? '%' : ''); // Add % to Y-axis labels
                                                }
                                            }
                                        }
                                    }
                                },
                                // plugins: [ChartDataLabels] // Enable plugin if used
                            };

                            // Render Chart
                            chartInstances[canvasId] = new Chart(document.getElementById(canvasId), config);

                            // Display Suggestions
                            if (suggestionsContainerId && response.suggestions) {
                                displaySuggestions(suggestionsContainerId, response.suggestions);
                            }

                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error(`Error fetching ${endpoint}:`, textStatus, errorThrown, jqXHR.responseText);
                            $(`#${suggestionsContainerId}`).html(`Error loading data for ${chartLabel}. Please check console or try again later.`).addClass('alert-danger');
                        });
                }

                // Function to load CO-PO Matrix Table
                function loadCoPoMatrix(containerId, suggestionsContainerId) {
                    const container = $(`#${containerId}`);
                    container.html('<p>Loading matrix data...</p>'); // Show loading state

                    $.getJSON(`facciaanalysis2.class.php?action=co_po_matrix&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            if (!response || !response.data || response.data.length === 0) {
                                container.html('<p class="text-muted">No CO-PO mapping data available for this subject or assessment scope.</p>');
                                if (response.suggestions) displaySuggestions(suggestionsContainerId, response.suggestions);
                                return;
                            }

                            let tableHtml = '<table id="coPoMatrixTable" class="table table-bordered table-striped table-hover">';
                            tableHtml += '<thead class="table-light"><tr><th>CO Label</th><th>CO Attainment (%)</th><th>Mapped PO</th><th>Weightage</th></tr></thead><tbody>';

                            response.data.forEach(item => {
                                // Apply conditional styling for low CO attainment
                                let attainmentClass = '';
                                let attainmentDisplay = item.co_attainment;
                                if (attainmentDisplay !== 'N/A' && parseFloat(attainmentDisplay) < TARGET_THRESHOLD) {
                                    attainmentClass = 'low-attainment';
                                }
                                if (attainmentDisplay !== 'N/A') attainmentDisplay += '%'; // Add % sign


                                tableHtml += `<tr>
                                                <td>${item.co_label}</td>
                                                <td class="${attainmentClass}">${attainmentDisplay}</td>
                                                <td>${item.po_label}</td>
                                                <td>${item.weightage}</td>
                                            </tr>`;
                            });

                            tableHtml += '</tbody></table>';
                            container.html(tableHtml);

                            // Display Suggestions
                            if (suggestionsContainerId && response.suggestions) {
                                displaySuggestions(suggestionsContainerId, response.suggestions);
                            }
                        })
                        .fail(function(jqXHR, textStatus, errorThrown) {
                            console.error('Error fetching CO-PO Matrix data:', textStatus, errorThrown, jqXHR.responseText);
                            container.html('<p class="text-danger">Error loading CO-PO Matrix data.</p>');
                            $(`#${suggestionsContainerId}`).html('Error loading suggestions for CO-PO Matrix.').addClass('alert-danger');
                        });
                }

                function loadCOSpreadChart() {
                    $.getJSON(`facciaanalysis2.class.php?action=co_spread&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            if (!response.data || response.data.length === 0) {
                                $('#coSpreadChart').hide();
                                $('#coSpreadSuggestions').html('No data available.').addClass('alert-secondary');
                                return;
                            }

                            const labels = response.data.map(r => r.co_label);
                            const data = response.data.map(r => r.avg_attainment);

                            if (chartInstances['coSpreadChart']) chartInstances['coSpreadChart'].destroy();
                            chartInstances['coSpreadChart'] = new Chart(document.getElementById('coSpreadChart'), {
                                type: 'bar',
                                data: {
                                    labels: labels,
                                    datasets: [{
                                        label: 'Avg Student-wise CO Distribution',
                                        data: data,
                                        backgroundColor: generateDistinctColors(data.length)
                                    }]
                                },
                                options: {
                                    responsive: true,
                                    plugins: {
                                        legend: {
                                            display: true
                                        }
                                    },
                                    scales: {
                                        y: {
                                            beginAtZero: true,
                                            max: 100
                                        }
                                    }
                                }
                            });

                            displaySuggestions('coSpreadSuggestions', response.suggestions);
                        });
                }

                function loadQuestionDifficultyTable() {
                    $.getJSON(`facciaanalysis2.class.php?action=question_difficulty&sub_id=${SUB_ID}&assessment_number=${ASSESSMENT_NUMBER}`)
                        .done(function(response) {
                            const container = $('#questionDifficultyTable');
                            if (!response.data || response.data.length === 0) {
                                container.html('<p class="text-muted">No difficulty data available.</p>');
                                return;
                            }

                            let html = '<table class="table table-bordered table-striped table-sm">';
                            let theadRow = '<tr><th>#</th>';
                            if (ASSESSMENT_NUMBER === 'all') {
                                theadRow += '<th>Assessment</th>';
                            }
                            theadRow += '<th>Component</th><th>Question</th>';
                            theadRow += '<th>Avg Difficulty (%)</th><th>Std. Deviation</th></tr>';
                            html += '<thead>' + theadRow + '</thead><tbody>';

                            response.data.forEach((q, i) => {
                                const diff = parseFloat(q.avg_difficulty);
                                const cls = diff < TARGET_THRESHOLD ? 'table-danger' : '';
                                let componentDisplay = '';
                                if (q.component_type == "Assignment") {
                                    componentDisplay = `Assignment`;
                                } else {
                                    componentDisplay = q.component_type.charAt(0).toUpperCase() + q.component_type.slice(1);
                                }

                                let row = `<tr class="${cls}"><td>${i + 1}</td>`;
                                if (ASSESSMENT_NUMBER === 'all') {
                                    row += `<td>${q.assessment_number}</td>`;
                                }
                                row += `<td>${componentDisplay}</td>`;
                                if (q.component_type == "Assignment") {
                                    row += `<td>${q.sequence_number}</td>`;
                                } else {
                                    row += `<td>${q.question_text}</td>`;
                                }
                                row += `<td>${diff}%</td><td>${parseFloat(q.std_dev).toFixed(2)}</td></tr>`;
                                html += row;
                            });

                            html += '</tbody></table>';
                            container.html(html);
                            displaySuggestions('questionDiffSuggestions', response.suggestions);
                        });
                }

                // Load all charts and data when the document is ready AND subject/assessment selected
                $(document).ready(function() {
                    if (SUB_ID && ASSESSMENT_NUMBER) {
                        // Load charts for each tab
                        loadChart('co_attainment', 'coChart', 'co_label', 'co_attainment_percentage', 'CO Attainment', 'bar', true, 'coChartSuggestions');
                        loadChart('po_attainment', 'poChart', 'po_label', 'po_attainment_percentage', 'PO Attainment', 'bar', true, 'poChartSuggestions');
                        loadChart('blooms_performance', 'bloomsChart', 'blooms_label', 'attainment_percentage', "Bloom's Attainment", 'bar', true, 'bloomsChartSuggestions');
                        // Assessment performance always shows all assessments for the subject
                        loadChart('assessment_performance', 'assessmentChart', 'assessment_label', 'attainment_percentage', 'Assessment Performance Trend', 'bar', true, 'assessmentChartSuggestions'); // Changed to line chart
                        loadChart('component_performance', 'componentChart', 'component_type', 'attainment_percentage', 'Component Performance', 'pie', true, 'componentChartSuggestions'); // Changed to pie chart

                        // Load CO-PO Matrix Table
                        loadCoPoMatrix('coPoMatrixTableContainer', 'coPoMatrixSuggestions');

                        loadCOSpreadChart();
                        loadQuestionDifficultyTable(); // function below


                        // Add listener to redraw charts if tab becomes visible (useful if rendering issues occur)
                        $('button[data-bs-toggle="tab"]').on('shown.bs.tab', function(e) {
                            const targetPaneId = $(e.target).attr('data-bs-target');
                            const canvasId = $(targetPaneId).find('canvas').attr('id');
                            if (canvasId && chartInstances[canvasId]) {
                                // Optional: Force resize/render if needed, but Chart.js 4 often handles this well
                                // chartInstances[canvasId].resize();
                            } else if (targetPaneId === '#copo-matrix-tab-pane') {
                                // Reload matrix if needed, or just ensure it's visible
                            }
                        });
                    } else {
                        // Optionally display a message if no subject/assessment selected
                        $('#analysisTabContent').html('<p class="text-center text-muted">Please select a subject and assessment to view the analysis.</p>');
                    }

                    // Enable assessment dropdown only when a subject is selected
                    $('#sub_id').on('change', function() {
                        if ($(this).val()) {
                            $('#assessment_number').prop('disabled', false).prop('required', true);
                            // Optionally trigger form submit here if you want auto-load on subject change
                            //$('#analysisSelectionForm').submit();
                        } else {
                            $('#assessment_number').prop('disabled', true).prop('required', false).val('');
                        }
                    });
                });
    </script>
    HTML;

    // Replace placeholders with PHP values
    return str_replace(
        ['__THRESHOLD__', '__SUB_ID__','__ASSESSMENT__'],
        [$thresholdJson, $subIdJson, $assessmentJson],
        $script
    );
}