<div class="tab-pane fade <?= $tab_department_active; ?>" id="department" role="tabpanel">
    <form class="mt-3" id="selectionForm" method="post" action="superadminpopso.php">
        <input type="hidden" name="active_tab" value="department">
        
        <div class="card">
            <div class="card-header bg-light">
                <h5 class="mb-0"><i class="fas fa-filter"></i> Select Criteria</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="acad_year">Academic Year: <span class="text-danger">*</span></label>
                            <select name="acad_year" id="acad_year" class="form-select" required>
                                <option value="">Select Academic Year</option>
                                <?php if (!empty($academic_years)): foreach ($academic_years as $year): ?>
                                    <option value="<?= htmlspecialchars($year['acad_year']); ?>" <?= $year['acad_year'] == $acad_year ? 'selected' : ''; ?>><?= htmlspecialchars($year['acad_year']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="prog_id_dept">Program: <span class="text-danger">*</span></label>
                            <select name="prog_id" id="prog_id_dept" class="form-select" required>
                                <option value="">Select Program</option>
                                <?php if (isset($programs['data']) && !empty($programs['data'])): foreach ($programs['data'] as $prog): ?>
                                    <option value="<?= htmlspecialchars($prog['id']); ?>" <?= $prog['id'] == $prog_id ? 'selected' : ''; ?>><?= htmlspecialchars($prog['prog_shortname']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="dept_id_dept">Department: <span class="text-danger">*</span></label>
                            <select name="dept_id" id="dept_id_dept" class="form-select" required>
                                <option value="">Select Department</option>
                                <?php if (isset($departments['data']) && !empty($departments['data'])): foreach ($departments['data'] as $dept): ?>
                                    <option value="<?= htmlspecialchars($dept['id']); ?>" <?= $dept['id'] == $dept_id ? 'selected' : ''; ?>><?= htmlspecialchars($dept['dept_shortname']); ?></option>
                                <?php endforeach; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label for="regulation">Regulation: <span class="text-danger">*</span></label>
                            <input type="text" name="regulation" id="regulation" class="form-control" value="<?= htmlspecialchars($regulation); ?>" placeholder="e.g., R20" required>
                        </div>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label><i class="fas fa-check-square"></i> Specializations (select all that apply): <span class="text-danger">*</span></label>
                            <div id="specializations-container-dept" class="border rounded p-3 bg-light" style="min-height: 120px; max-height: 200px; overflow-y: auto;">
                                <small class="text-muted">Please select a program and department first.</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <button type="submit" name="action" value="fetch_details" class="btn btn-primary">
                        <i class="fas fa-cog"></i> Manage POs/PSOs
                    </button>
                </div>
            </div>
        </div>
    </form>
    
    <?php if ($show_details_for_dept_wise && !empty($spec_ids)): ?>
        <hr class="my-4">
        <?php require __DIR__ . '/popso_management_forms.php'; ?>
    <?php endif; ?>
</div>
