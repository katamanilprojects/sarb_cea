<?php
session_start();
$page_title = "Classes";
require_once("hod.class.php");
require_once("hodheader.php");

// Redirect if class_id is not provided
if (empty($_POST['class_id'])) {
    header("Location: hodviewclasses.php");
    exit();
}

$obj = new HOD();
$class_id = $_POST['class_id'];

// Handle form submission
if (!empty($_POST['secretcode']) && $_POST['secretcode'] == $_SESSION['secretcode']) {
    unset($_SESSION['secretcode']);
    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "editsubcode" && !empty($_POST['subject_id']) && !empty($_POST['new_subcode'])) {
        $res_arr = $obj->updateSubjectCode($_POST['subject_id'], $_POST['new_subcode'], $class_id);
        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Subject code updated successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to update subject code. Please try again.";
        }
    }

    if (!empty($_POST['subject_sno']) && !empty($_POST['subcode']) && !empty($_POST['sub_shortname']) && !empty($_POST['sub_fullname']) && !empty($_POST['sub_type']) && !empty($_POST['class_id'])) {
        $numBatches = !empty($_POST['num_batches']) ? (int)$_POST['num_batches'] : 1;

        if ($numBatches <= 1) {
            // Single batch subject
            $res_arr = $obj->addSubject($_POST);
            if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
                $msg = "Subject added successfully.";
            } else {
                $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to add subject. Please try again.";
            }
        } else {
            // Multi-batch subject: generate standard suffixed records (Group A, Group B, ...)
            $letters = ['a', 'b', 'c', 'd', 'e', 'f'];
            $groupNames = ['Group A', 'Group B', 'Group C', 'Group D', 'Group E', 'Group F'];
            $successCount = 0;
            $errors = [];

            for ($b = 0; $b < $numBatches; $b++) {
                $suffix = $letters[$b];
                $grpLabel = $groupNames[$b];

                $batchData = $_POST;
                $batchData['subcode'] = trim($_POST['subcode']) . $suffix;
                $batchData['sub_fullname'] = trim($_POST['sub_fullname']) . " (" . $grpLabel . ")";
                // Short name with group suffix, bounded to 20 chars
                $baseShort = trim($_POST['sub_shortname']);
                $batchData['sub_shortname'] = substr($baseShort . "-" . strtoupper($suffix), 0, 20);

                $bRes = $obj->addSubject($batchData);
                if (!empty($bRes['status']) && $bRes['status'] == 1) {
                    $successCount++;
                } else {
                    $errors[] = (!empty($bRes['err'])) ? $bRes['err'] : "Failed for " . $batchData['subcode'];
                }
            }

            if ($successCount === $numBatches) {
                $msg = "All " . $numBatches . " batches added successfully (" . implode(", ", array_slice($groupNames, 0, $numBatches)) . ").";
            } elseif ($successCount > 0) {
                $msg = $successCount . " of " . $numBatches . " batches added. Errors: " . implode("; ", $errors);
            } else {
                $msg = "Failed to add batches: " . implode("; ", $errors);
            }
        }
    }

    if (!empty($_POST['whattodo']) && $_POST['whattodo'] == "deletesubject" && !empty($_POST['subject_id'])) {

        $res_arr = $obj->deleteSubjectByID($_POST['subject_id']);

        if (!empty($res_arr['status']) && $res_arr['status'] == 1) {
            $msg = "Subject Deleted successfully.";
        } else {
            $msg = (!empty($res_arr['err'])) ? $res_arr['err'] : "Failed to delete subject. Please try again.";
        }
    }
}

// Generate new secret code
$_SESSION['secretcode'] = bin2hex(random_bytes(32));

// Fetch students for the selected class
$subjectList = $obj->getSubjectsByClassID($class_id);

// Fetch class info to load central curriculum subjects
$classInfo = $obj->getClassById($class_id);
$curriculumSubjects = [];
if (!empty($classInfo) && !empty($classInfo['reg_id']) && !empty($classInfo['spec_id']) && !empty($classInfo['yearsem'])) {
    require_once("curriculum_subject.class.php");
    $currObj = new CurriculumSubject();
    $currRes = $currObj->getSubjectsForClass($classInfo['reg_id'], $classInfo['spec_id'], $classInfo['yearsem']);
    $curriculumSubjects = $currRes['data'] ?? [];
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            <div class="float-start">
                Specialization: <?= htmlspecialchars($_POST['spec_fullname']) ?>
            </div>
            <div class="float-end">
                <a href="hodviewclasses.php" class="btn btn-sm btn-outline-success">All Classes</a>
            </div>
        </div>
        <div class="card-header">
            Class: <?= htmlspecialchars($_POST['class_fullname']) ?>
        </div>
    </div>
</div>
<div class="container">
    <div class="row">
        <div class="col-sm-6">
            <div class="card">
                <div class="card-header">Add New Subject</div>
                <div class="card-body">
                    <form action="hodviewsubjects.php" method="post">
                        <?php if (!empty($curriculumSubjects)): ?>
                            <div class="form-group mb-3 p-2 bg-light border rounded">
                                <label for="curr_sub_picker" class="fw-bold text-primary">
                                    <i class="bi bi-journal-bookmark-fill me-1"></i>Select from Curriculum / Syllabus:
                                </label>
                                <select id="curr_sub_picker" class="form-select form-select-sm" onchange="applyCurriculumSubject(this)">
                                    <option value="">-- Choose Predefined Subject --</option>
                                    <?php foreach ($curriculumSubjects as $cs): ?>
                                        <option value="<?= htmlspecialchars($cs['subcode']) ?>"
                                                data-sno="<?= htmlspecialchars($cs['subject_sno']) ?>"
                                                data-subcode="<?= htmlspecialchars($cs['subcode']) ?>"
                                                data-fullname="<?= htmlspecialchars($cs['sub_fullname']) ?>"
                                                data-shortname="<?= htmlspecialchars($cs['sub_shortname']) ?>"
                                                data-type="<?= htmlspecialchars($cs['sub_type']) ?>">
                                            <?= htmlspecialchars($cs['subject_sno'] . '. [' . $cs['subcode'] . '] ' . $cs['sub_fullname'] . ' (' . $cs['sub_type'] . ')') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Selecting auto-fills S.No, code, name, and type.</small>
                            </div>
                        <?php endif; ?>
                        <div class="form-group">
                            <label for="subject_sno">Subject S.No. (Based on Syllabus/Curriculum)</label>
                            <select name="subject_sno" id="subject_sno" class="form-select" required>
                                <option value="">--Select Subject S.No.--</option>
                                <?php
                                for ($i = 1; $i < 13; $i++) {
                                    echo '<option value="' . $i . '">' . $i . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="form-group position-relative">
                            <label for="subcode">Subject Code:</label>
                            <div class="input-group">
                                <input type="text" name="subcode" id="subcode" class="form-control text-uppercase" required autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary" onclick="lookupSubjectCodeHod()" title="Lookup catalog details">
                                    <i class="bi bi-search" id="hodLookupIcon"></i>
                                </button>
                            </div>
                            <small id="hodLookupStatus" class="form-text text-muted"></small>
                        </div>
                        <input type="hidden" id="hod_reg_id" value="<?= htmlspecialchars($classInfo['reg_id'] ?? '') ?>">
                        <div class="form-group">
                            <label for="sub_fullname">Subject Full Name:</label>
                            <input type="text" name="sub_fullname" id="sub_fullname" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_shortname">Subject Short Name:</label>
                            <input type="text" name="sub_shortname" id="sub_shortname" class="form-control" maxlength="20" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_type">Subject Type:</label>
                            <select name="sub_type" id="sub_type" class="form-select" required onchange="onSubTypeChange(this)">
                                <option value="Theory">Theory</option>
                                <option value="Lab">Lab</option>
                                <option value="PE">Professional Elective</option>
                                <option value="OE">Open Elective</option>
                                <option value="OE">Humanities Elective</option>
                                <option value="Skill">Skill Oriented Course</option>
                                <option value="MNCC">Mandatory Not-Credit Course</option>
                                <option value="dti">Design Thinking & Innovation</option>
                            </select>
                        </div>
                        <div class="form-group mt-2 p-2 border rounded bg-light">
                            <label for="num_batches" class="fw-bold text-dark">
                                <i class="bi bi-people-fill me-1 text-primary"></i>Batching / Groups:
                            </label>
                            <select name="num_batches" id="num_batches" class="form-select form-select-sm" onchange="updateBatchPreview()">
                                <option value="1">1 Batch - Whole Class (Default)</option>
                                <option value="2">2 Batches (Group A, Group B)</option>
                                <option value="3">3 Batches (Group A, Group B, Group C)</option>
                                <option value="4">4 Batches (Group A, Group B, Group C, Group D)</option>
                            </select>
                            <div id="batchPreviewBox" class="small mt-1 text-muted" style="display: none;"></div>
                        </div>
                        <br />
                        <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                        <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                        <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                        <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                        <button type="submit" class="btn btn-primary" id="submitSubjectBtn">Add Subject</button>
                    </form>
                    <?php if (isset($msg)) echo '<div class="alert alert-info">' . $msg . '</div>'; ?>
                </div>
            </div>
        </div>
        <div class="col-sm-6">
            <div class="card">
                <div class="card-header">Important points</div>
                <div class="card-body">
                    <ul>
                        <li>The <u>Subject S.No</u>, <u>Subject Code</u> and <u>Full Name</u> should match the S.No, SubCode and Subject Name used in the <strong>Academic Syllabus</strong>.<br />(<strong>Same S.No</strong> will be present for the elective subjects)</li>
                        <br />
                        <li><strong>Automated Batch / Group Generator:</strong>
                            <ul>
                                <li>For Labs or practical courses divided into groups, select <strong>2, 3, or 4 Batches</strong> from the dropdown above.</li>
                                <li>The system will <strong>automatically generate</strong> standard-compliant entries (e.g. <code>ABC123a: Lab (Group A)</code> and <code>ABC123b: Lab (Group B)</code>) in a single click.</li>
                                <li>This ensures 100% compatibility with Attendance Marking, Faculty Allocation, Timetable Scheduling, and CIA Analysis.</li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    <br>
    <div class="card">
        <div class="card-header">Display Subjects</div>
        <div class="card-body">
            <table class="table table-bordered">
                <thead>
                    <tr style='border-top: 1px solid #000;'>
                        <th>S.No.</th>
                        <th>Code</th>
                        <th>Short Name</th>
                        <th>Full Name</th>
                        <th>Type</th>
                        <th></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (!empty($subjectList['status']) && $subjectList['status'] == 1) {
                        $sno = "";
                        foreach ($subjectList['data'] as $subject) {
                            echo '<tr>';
                            if ($sno == $subject['subject_sno']) {
                                $style = '';
                            } else {
                                $style = " style= 'border-top: 1px solid #000;'";
                                $sno = $subject['subject_sno'];
                            }
                            echo "<td {$style}>{$subject['subject_sno']}</td>";
                            echo "<td {$style}>{$subject['subcode']}</td>";                            
                            echo "<td {$style}>{$subject['sub_shortname']}</td>";
                            echo "<td {$style}>{$subject['sub_fullname']}</td>";
                            echo "<td {$style}>{$subject['sub_type']}</td>";
                            echo "<td {$style}>";
                    ?>
                            <form action="hodviewsubjects.php" method="post" onsubmit="return confirm('Are you sure you want to Delete Subject:  <?= htmlspecialchars($subject['subcode'] ?? ''); ?> ?');">
                                <input type="hidden" name="class_id" value="<?php echo $_POST["class_id"]; ?>" />
                                <input type="hidden" name="class_fullname" value="<?php echo $_POST["class_fullname"]; ?>" />
                                <input type="hidden" name="spec_fullname" value="<?php echo $_POST["spec_fullname"] ?>" />
                                <input type="hidden" name="secretcode" value="<?php echo $_SESSION['secretcode']; ?>">
                                <input type="hidden" name="subject_id" value="<?php echo $subject["id"]; ?>" />
                                <input type="hidden" name="whattodo" value="deletesubject" />
                                <input type="submit" class="btn btn-outline-danger" value="X" />
                            </form>
                    <?php
                            echo "</td>";
                            echo "<td {$style}>";                                                
                            echo "<button type='button' class='btn btn-primary btn-sm ml-2' onclick='editSubcode({$subject['id']}, \"{$subject['subcode']}\")'>
                                <i class='fa fa-edit'></i> Edit SubCode</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                    } else {
                        echo "<tr><td colspan='7'>No subjects found.</td></tr>";
                    }
                    ?>
                </tbody>
                <script>
                    function editSubcode(subjectId, currentSubcode) {
                        var newSubcode = prompt("Edit Subject Code:", currentSubcode);

                        if (newSubcode !== null && newSubcode.trim() !== "" && newSubcode !== currentSubcode) {
                            var form = document.createElement('form');
                            form.method = 'POST';
                            form.action = '';

                            var whattodoInput = document.createElement('input');
                            whattodoInput.type = 'hidden';
                            whattodoInput.name = 'whattodo';
                            whattodoInput.value = 'editsubcode';
                            form.appendChild(whattodoInput);

                            var subjectIdInput = document.createElement('input');
                            subjectIdInput.type = 'hidden';
                            subjectIdInput.name = 'subject_id';
                            subjectIdInput.value = subjectId;
                            form.appendChild(subjectIdInput);

                            var subcodeInput = document.createElement('input');
                            subcodeInput.type = 'hidden';
                            subcodeInput.name = 'new_subcode';
                            subcodeInput.value = newSubcode.trim();
                            form.appendChild(subcodeInput);

                            var secretcodeInput = document.createElement('input');
                            secretcodeInput.type = 'hidden';
                            secretcodeInput.name = 'secretcode';
                            secretcodeInput.value = '<?php echo $_SESSION['secretcode']; ?>';
                            form.appendChild(secretcodeInput);

                            // Add class_id to maintain context
                            var classIdInput = document.createElement('input');
                            classIdInput.type = 'hidden';
                            classIdInput.name = 'class_id';
                            classIdInput.value = '<?php echo $class_id; ?>';
                            form.appendChild(classIdInput);

                            document.body.appendChild(form);
                            form.submit();
                        }
                    }

                    function onSubTypeChange(selectEl) {
                        const batchSel = document.getElementById('num_batches');
                        if (selectEl.value.toLowerCase() === 'lab') {
                            if (batchSel && batchSel.value === "1") {
                                batchSel.value = "2"; // Suggest 2 batches by default for labs
                            }
                        }
                        updateBatchPreview();
                    }

                    function updateBatchPreview() {
                        const numBatches = parseInt(document.getElementById('num_batches').value) || 1;
                        const previewBox = document.getElementById('batchPreviewBox');
                        const submitBtn = document.getElementById('submitSubjectBtn');
                        const code = (document.getElementById('subcode').value || 'CODE').trim();
                        const name = (document.getElementById('sub_fullname').value || 'Subject Name').trim();

                        if (numBatches <= 1) {
                            if (previewBox) {
                                previewBox.style.display = 'none';
                                previewBox.innerHTML = '';
                            }
                            if (submitBtn) submitBtn.textContent = 'Add Subject';
                            return;
                        }

                        const letters = ['a', 'b', 'c', 'd', 'e', 'f'];
                        const groupNames = ['Group A', 'Group B', 'Group C', 'Group D', 'Group E', 'Group F'];
                        let html = '<strong>Will automatically generate ' + numBatches + ' subjects:</strong><ul class="mb-0 ps-3 mt-1">';

                        for (let i = 0; i < numBatches; i++) {
                            const subCode = code + letters[i];
                            const subName = name + ' (' + groupNames[i] + ')';
                            html += '<li><code>' + subCode + '</code>: ' + subName + '</li>';
                        }
                        html += '</ul>';

                        if (previewBox) {
                            previewBox.innerHTML = html;
                            previewBox.style.display = 'block';
                        }
                        if (submitBtn) {
                            submitBtn.textContent = 'Add ' + numBatches + ' Batches';
                        }
                    }

                    function applyCurriculumSubject(selectEl) {
                        if (!selectEl.value) return;
                        const opt = selectEl.options[selectEl.selectedIndex];
                        if (!opt) return;

                        const sno = opt.dataset.sno;
                        const code = opt.dataset.subcode;
                        const fullname = opt.dataset.fullname;
                        const shortname = opt.dataset.shortname;
                        const type = opt.dataset.type;

                        if (sno) {
                            const snoEl = document.getElementById('subject_sno');
                            if (snoEl) snoEl.value = sno;
                        }
                        if (code) {
                            const codeEl = document.getElementById('subcode');
                            if (codeEl) codeEl.value = code;
                        }
                        if (fullname) {
                            const fnEl = document.getElementById('sub_fullname');
                            if (fnEl) fnEl.value = fullname;
                        }
                        if (shortname) {
                            const snEl = document.getElementById('sub_shortname');
                            if (snEl) snEl.value = shortname;
                        }
                        if (type) {
                            const stEl = document.getElementById('sub_type');
                            if (stEl) {
                                let matched = false;
                                for (let i = 0; i < stEl.options.length; i++) {
                                    if (stEl.options[i].value.toLowerCase() === type.toLowerCase() ||
                                        stEl.options[i].text.toLowerCase() === type.toLowerCase()) {
                                        stEl.selectedIndex = i;
                                        matched = true;
                                        break;
                                    }
                                }
                                if (!matched) {
                                    const newOpt = new Option(type, type, true, true);
                                    stEl.add(newOpt);
                                }
                            }

                            const batchSel = document.getElementById('num_batches');
                            if (type.toLowerCase() === 'lab') {
                                if (batchSel) batchSel.value = "2";
                            } else {
                                if (batchSel) batchSel.value = "1";
                            }
                        }
                        updateBatchPreview();
                    }

                    function lookupSubjectCodeHod() {
                        const subcodeInput = document.getElementById('subcode');
                        const subcode = subcodeInput ? subcodeInput.value.trim() : '';
                        const regIdEl = document.getElementById('hod_reg_id');
                        const regId = regIdEl ? regIdEl.value : '';
                        const statusEl = document.getElementById('hodLookupStatus');
                        const iconEl = document.getElementById('hodLookupIcon');

                        if (!subcode) return;

                        if (iconEl) iconEl.className = 'spinner-border spinner-border-sm';
                        if (statusEl) {
                            statusEl.className = 'form-text text-muted';
                            statusEl.textContent = 'Searching catalog...';
                        }

                        fetch('curriculum_subject_ajax.php?action=lookup_code&subcode=' + encodeURIComponent(subcode) + '&reg_id=' + encodeURIComponent(regId))
                            .then(r => r.json())
                            .then(res => {
                                if (iconEl) iconEl.className = 'bi bi-search';
                                if (res.status === 1 && res.data) {
                                    const d = res.data;
                                    if (statusEl) {
                                        statusEl.className = 'form-text text-success fw-bold';
                                        statusEl.textContent = '✓ Details auto-retrieved from catalog!';
                                    }
                                    if (d.sub_fullname) document.getElementById('sub_fullname').value = d.sub_fullname;
                                    if (d.sub_shortname) document.getElementById('sub_shortname').value = d.sub_shortname;
                                    if (d.sub_type) {
                                        const stEl = document.getElementById('sub_type');
                                        if (stEl) {
                                            for (let i = 0; i < stEl.options.length; i++) {
                                                if (stEl.options[i].value.toLowerCase() === d.sub_type.toLowerCase() ||
                                                    stEl.options[i].text.toLowerCase() === d.sub_type.toLowerCase()) {
                                                    stEl.selectedIndex = i;
                                                    break;
                                                }
                                            }
                                        }
                                        const batchSel = document.getElementById('num_batches');
                                        if (d.sub_type.toLowerCase() === 'lab') {
                                            if (batchSel) batchSel.value = "2";
                                        } else {
                                            if (batchSel) batchSel.value = "1";
                                        }
                                    }
                                    updateBatchPreview();
                                } else {
                                    if (statusEl) {
                                        statusEl.className = 'form-text text-muted';
                                        statusEl.textContent = 'New subject code (enter details below)';
                                    }
                                }
                            })
                            .catch(err => {
                                if (iconEl) iconEl.className = 'bi bi-search';
                                if (statusEl) {
                                    statusEl.className = 'form-text text-danger';
                                    statusEl.textContent = 'Lookup error';
                                }
                            });
                    }

                    // Attach live listeners for code and name updates to keep batch preview in sync & debounce lookup
                    let lookupTimerHod = null;
                    document.addEventListener('DOMContentLoaded', function() {
                        const subCodeEl = document.getElementById('subcode');
                        const subNameEl = document.getElementById('sub_fullname');
                        if (subCodeEl) {
                            subCodeEl.addEventListener('input', function() {
                                updateBatchPreview();
                                clearTimeout(lookupTimerHod);
                                const val = this.value.trim();
                                if (val.length >= 3) {
                                    lookupTimerHod = setTimeout(lookupSubjectCodeHod, 600);
                                }
                            });
                            subCodeEl.addEventListener('blur', lookupSubjectCodeHod);
                        }
                        if (subNameEl) subNameEl.addEventListener('input', updateBatchPreview);
                    });
                </script>

            </table>
        </div>
    </div>
</div>

<?php
require_once("hodfooter.php");
?>