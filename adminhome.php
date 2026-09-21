<?php
session_start();
$page_title = "Home";
require_once("adminheader.php");
?>

<div class="container">
	<br />
	<div class="card">
		<div class="card-header">
			Instructions
		</div>
		<div class="card-body">

			<h5>Welcome to the Administrator dashboard</h5>

			<p>This portal provides you with the tools to manage Student Academic Record Book Software.</p>
			<br />
			<h5>Menu Options</h5>

			<ul>
				<li>
					<strong>Departments:</strong> 
						<ul>
							<li>You can Add, View,  and Update <u>Faculty Details</u> of various Departments offered by the institution.</li>
							<li>Enroll Students for Each Class Offered by the various Departments</li>
							<li>View and Update Head of the Department User Profiles</li>
						</ul>
				</li>
				<br />
				<li>
					<strong>Show Attendance:</strong> Access attendance records (Student Academic Record Books) of faculty across different departments. You can view overall or detailed attendance reports for subjects handled by faculty members, and even download detailed reports.
				</li>
				<br />
				<li><em><strong>.</strong> For Future Use</em></li>
				<br />
				<li>
					<strong>Change Password:</strong> Update your account password.
				</li>
				<li>
					<strong>Logout:</strong> Safely log out of your account.
				</li>
			</ul>
			<br />

			<h5>Additional Notes</h5>
			<ul>
				<li>If you encounter any issues, please notify the same.</li>
			</ul>
		</div>
	</div>
</div>

<?php
require_once("adminfooter.php");
?>