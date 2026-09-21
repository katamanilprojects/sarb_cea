<div class="container dontprint">
    <div class="row">
        <div class="col-6 col-sm-2 p-0">
            <a href="fachome.php" class="w-100 btn <?php if (!empty($page_title) && $page_title == "Home") {
                                                        echo "btn-success";
                                                    } else {
                                                        echo "btn-outline-primary";
                                                    } ?>">
                <i class="bi bi-house-door me-1"></i> Home
            </a>
        </div>
        <div class="col-6 col-sm-2 p-0">
            <a href="facaddattendance.php" class="w-100 btn <?php if (!empty($page_title) && $page_title == "Mark Attendance") {
                                                                echo "btn-success";
                                                            } else {
                                                                echo "btn-outline-primary";
                                                            } ?>">
                <i class="bi bi-pencil-square me-1"></i> Mark Attendance
            </a>
        </div>
        <div class="col-6 col-sm-2 p-0">
            <a href="facshowattendance.php" class="w-100 btn <?php if (!empty($page_title) && $page_title == "View Attendance") {
                                                                    echo "btn-success";
                                                                } else {
                                                                    echo "btn-outline-primary";
                                                                } ?>">
                <i class="bi bi-calendar-check me-1"></i> Show Attendance
            </a>
        </div>
        <?php if ($_SESSION['user'] == "ekr"): ?>
            <div class="col-6 col-sm-2 p-0">
                <a href="faceditoptions.php" class="w-100 btn <?php if (!empty($page_title) && $page_title == "Edit") {
                                                                    echo "btn-success";
                                                                } else {
                                                                    echo "btn-outline-primary";
                                                                } ?>">
                    <i class="bi bi-tools me-1"></i> Other Options
                </a>
            </div>
        <?php else: ?>
            <div class="col-6 col-sm-2 p-0">
                <a href="faceditoptions.php" class="w-100 btn <?php if (!empty($page_title) && $page_title == "Edit") {
                                                                    echo "btn-success";
                                                                } else {
                                                                    echo "btn-outline-primary";
                                                                } ?>">
                    <i class="bi bi-tools me-1"></i> Other Options
                </a>
            </div>
        <?php endif; ?>
        <div class="col-6 col-sm-2 p-0">
            <a href="facchgpwd.php" class="w-100 btn <?php if (!empty($page_title) && $page_title == "Change Pwd") {
                                                            echo "btn-success";
                                                        } else {
                                                            echo "btn-outline-primary";
                                                        } ?>">
                <i class="bi bi-key me-1"></i> Change Password
            </a>
        </div>
        <div class="col-6 col-sm-2 p-0">
            <a href="logout.php" class="w-100 btn btn-outline-primary">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </a>
        </div>
    </div>
</div>
<hr>