<?php
session_start();
$page_title = "Manage Delete Requests";
require_once("hod.class.php");
require_once("hodheader.php");

$hodObj = new HOD();
$dept_id = $_SESSION["dept_id"];

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['process_request'])) {
    $request_id = $_POST['request_id'];
    $action = $_POST['process_request'];
    $result = $hodObj->processDeleteRequest($request_id, $action);

    if ($result['status'] == 1) {
        $msg = "Request successfully " . ($action == "Approved" ? "approved." : "rejected.");
    } else {
        $error = "Failed to process the request. Please try again.";
    }
}

$deleteRequests = $hodObj->getDeleteRequestsByDepartment($dept_id);

$pending = false;
$present = false;

if (!empty($deleteRequests['data'])) {
    foreach ($deleteRequests['data'] as $request) {
        $present = true;
        if ($request['status'] == 'Pending') {
            $pending = true;
            break;
        }
    }
}
?>

<div class="container">
    <div class="card">
        <div class="card-header">
            Pending - Attendance and Diary Delete Requests
        </div>
        <div class="card-body">
            <?php if ($pending): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Request Date</th>
                                <th>Approval Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deleteRequests['data'] as $request): ?>
                                <?php if ($request['status'] == 'Pending'): ?>
                                    <?php $hour_desc = $hodObj->getHourDescById($request['hour']); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['faculty_name']) ?></td>
                                        <td><?= htmlspecialchars($request['subject']) ?></td>
                                        <td><?= htmlspecialchars($request['date']) ?></td>
                                        <td><?= htmlspecialchars($hour_desc) ?></td>
                                        <td><?= htmlspecialchars($request['reason']) ?></td>
                                        <td><?= htmlspecialchars($request['status']) ?></td>
                                        <td><?= htmlspecialchars($request['request_date']) ?></td>
                                        <td>
                                            <form action="hodviewdelrequests.php" method="post" id="form<?= $request['id'] ?>">
                                                <input type="hidden" name="request_id" value="<?= $request['id'] ?>">
                                                <button type="button" onclick="confirmRequest('Approve', 'form<?= $request['id'] ?>')" class="btn btn-success btn-sm">Approve</button>
                                                <button type="button" onclick="confirmRequest('Reject', 'form<?= $request['id'] ?>')" class="btn btn-danger btn-sm">Reject</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No pending delete requests found.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (isset($msg)): ?>
        <div class="alert alert-success mt-3"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
        <div class="alert alert-danger mt-3"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <br>
    <div class="card">
        <div class="card-header">
            Approved/Rejected - Attendance and Diary Delete Requests
        </div>
        <div class="card-body">
            <?php if ($present): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead>
                            <tr>
                                <th>Faculty</th>
                                <th>Subject</th>
                                <th>Date</th>
                                <th>Hour</th>
                                <th>Reason</th>
                                <th>Status</th>
                                <th>Aprroved/ Rejected Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($deleteRequests['data'] as $request): ?>
                                <?php if ($request['status'] != 'Pending'): ?>
                                    <?php $hour_desc = $hodObj->getHourDescById($request['hour']); ?>
                                    <tr>
                                        <td><?= htmlspecialchars($request['faculty_name']) ?></td>
                                        <td><?= htmlspecialchars($request['subject']) ?></td>
                                        <td><?= htmlspecialchars($request['date']) ?></td>
                                        <td><?= $hour_desc ?></td>
                                        <td><?= htmlspecialchars($request['reason']) ?></td>
                                        <td><?= htmlspecialchars($request['status']) ?></td>
                                        <td><?= htmlspecialchars($request['approval_date']) ?></td>
                                    </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p>No Approved/Rejected delete requests found.</p>
            <?php endif; ?>
        </div>
    </div>

</div>

<script>
    function confirmRequest(action, formId) {
        if (confirm(`Are you sure you want to ${action} the request?`)) {
            // Add a hidden input field to the form with the request ID and action
            const form = document.getElementById(formId);

            const hiddenActionInput = document.createElement('input');
            hiddenActionInput.type = 'hidden';
            hiddenActionInput.name = 'process_request';
            if (action == "Approve") {
                hiddenActionInput.value = 'Approved';
            }
            if (action == "Reject") {
                hiddenActionInput.value = 'Rejected';
            }
            form.appendChild(hiddenActionInput);

            form.submit();
        }
    }
</script>
<?php
require_once("hodfooter.php");
?>