<?php
session_start();
$page_title = "View Attendance";
require_once("adminheader.php");
?>

<div class="container">
	<br />
	<div class="card">
		<div class="card-header">
			Subject-Wise Student Academic Record Book
		</div>
		<div class="card-body">

			<ul>
				<li>
					<a href="adminshowfacattendance.php" class="btn btn-outline-primary">Attendance by Faculty </a>
				</li>
				<br>
				<li>
					<a href="adminshowclsattendance.php" class="btn btn-outline-success">Attendance by Class</a>
				</li>
			</ul>

		</div>
	</div>
	<br />
	<div class="card">
		<div class="card-header">
			Overall Class Attendance
		</div>
		<div class="card-body">

			<ul>
				<li>
					<a href="adminshowallclsattendance.php" class="btn btn-outline-success">Overall Class Attendance</a>
				</li>
			</ul>

		</div>
	</div>

	<br />
	<div class="card">
		<div class="card-header">
			Analysis & Feedback
		</div>
		<div class="card-body">

			<ul>
				<li>
					<a href="adminciaanalysis2.php" class="btn btn-outline-success">CIA Analysis</a>
				</li>
				<br>
				<li>
					<a href="adminviewfeedback.php" class="btn btn-outline-success">Feedback</a>
				</li>
			</ul>

		</div>
	</div>
</div>

<?php
require_once("adminfooter.php");
?>