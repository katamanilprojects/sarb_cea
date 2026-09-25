
<div class="container mt-5">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>Manage Regulations</span>
            <a href="superadminacademicsettings.php" class="btn btn-sm btn-primary">
                <i class="bi bi-sliders me-1"></i>Configure Academic Settings Engine
            </a>
        </div>
        <div class="card-body">

            <?php if (!empty($msg)) echo "<div class='alert alert-info'>$msg</div>"; ?>

            <form method="post" action="<?= htmlspecialchars($page_action) ?>" class="mb-4">
                <input type="hidden" name="action" value="add_or_update">
                <input type="hidden" name="id" value="<?= $edit_regulation['id'] ?? '' ?>">
                <div class="row">
                    <div class="col-md-5">
                        <input type="text" name="regulation" class="form-control" placeholder="e.g., R20" maxlength="4" value="<?= htmlspecialchars($edit_regulation['regulation'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-5">
                        <select name="prog_id" class="form-select" required>
                            <option value="">Select Program</option>
                            <?php foreach ($programs as $program): ?>
                                <option value="<?= $program['id'] ?>" <?= (isset($edit_regulation) && $edit_regulation['prog_id'] == $program['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($program['prog_shortname'] ?? $program['program_code'] ?? $program['id']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100"><?= isset($edit_regulation) ? 'Update' : 'Add' ?></button>
                    </div>
                </div>
            </form>

            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Program</th>
                        <th>Regulation</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($regulations as $regulation): ?>
                        <tr>
                            <td><?= htmlspecialchars($regulation['prog_shortname'] ?? $regulation['program_code'] ?? '') ?></td>
                            <td><?= htmlspecialchars($regulation['regulation']) ?></td>
                            <td>
                                <a href="<?= htmlspecialchars($page_action) ?>?edit=<?= $regulation['id'] ?>" class="btn btn-sm btn-primary">Edit</a>
                                <form method="post" action="<?= htmlspecialchars($page_action) ?>" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this regulation?');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $regulation['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
