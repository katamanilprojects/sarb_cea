<div class="container dontprint">
    <div class="row">
            <div class="col-6 col-sm-2 p-0">
                <a href="adminhome.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Home"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>">
                    <i class="bi bi-house-door me-1"></i> Home
                </a>
            </div>
            <div class="col-6 col-sm-2 p-0">
                <a href="adminfacst.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Departments"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>">
                <i class="bi bi-diagram-3 me-1"></i> Departments
                </a>
            </div>
            <div class="col-6 col-sm-2 p-0">
                <a href="adminshowattendance.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="View Attendance"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>">
                    <i class="bi bi-calendar-check me-1"></i> Attendance
                </a>
            </div>
            <div class="col-6 col-sm-2 p-0">
                <div class="dropdown w-100">
                    <button class="w-100 btn btn-outline-primary dropdown-toggle" type="button" id="resetPwdDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-key me-1"></i> Reset Pwd
                    </button>
                    <ul class="dropdown-menu w-100" aria-labelledby="resetPwdDropdown">
                        <li><a class="dropdown-item" href="adminresetstudentpwd.php"><i class="bi bi-person me-1"></i> Reset Student Pwd</a></li>
                        <li><a class="dropdown-item" href="adminresetfacultypwd.php"><i class="bi bi-briefcase me-1"></i> Reset Faculty Pwd</a></li>
                    </ul>
                </div>
            </div>
            <div class="col-6 col-sm-2 p-0">
                <a href="adminchgpwd.php" class="w-100 btn <?php if(!empty($page_title) && $page_title=="Change Pwd"){ echo "btn-success"; }else{ echo "btn-outline-primary"; } ?>">
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
<br />