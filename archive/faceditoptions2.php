<?php
session_start();
$page_title = "Edit";
require_once("facheader.php");
?>
<div class="container">
	<br />
	<div class="card">
		<div class="card-body">

			<div class="card">
				<div class="card-header">
					Diary Only without Attendance
				</div>
				<div class="card-body">
					<ul>
						<li>
							<a href="facadddairy.php" class="btn btn-outline-success">Click here to Add Diary Only without Attendance, if required (Eg: Suspended Classes, etc.)</a>
						</li>
						<br />
					</ul>
				</div>
			</div>

			<br>
			<div class="card">
				<div class="card-header">
					COs
				</div>
				<div class="card-body">
					<ul>
						<li>
							<a href="facaddcos.php" class="btn btn-outline-success">Click here for Adding/Viewing Course Outcomes</a>
						</li>
						<br />
					</ul>
				</div>
			</div>

			<br>
			<div class="card">
				<div class="card-header">
					CIA Analysis
				</div>
				<div class="card-body">
					<ul>
						<li>
							<a href="facciaanalysis.php" class="btn btn-outline-success">Click here for Viewing CIA Analysis</a>
						</li>
						<br />
					</ul>
				</div>
			</div>

			<br>
			<div class="card">
				<div class="card-header">
					Continuous Internal Assessment Marks & MetaData
				</div>
				<div class="card-body">
					<ul>
						<li>
							<a href="facciamarks.php" class="btn btn-outline-success">Click here for Continuous Internal Assessment Marks and their Related Attachments</a>
						</li>
						<br />
					</ul>
				</div>
			</div>

			<br>
			<div class="card">
				<div class="card-header text-danger">
					Request for Attendance Record Deletion
				</div>
				<div class="card-body">
					<ul>
						<li>
							<a href="facadddelattrequest.php" class="btn btn-outline-secondary">Click here to Submit Request to the HoD for Attendance Record Deletion</a>
						</li>
						<br />
						<li>
							<a href="facshowdelattrequests.php" class="btn btn-outline-secondary">Click here to View the Submitted Requests</a>
						</li>
						<br />
					</ul>
					<br />
				</div>
			</div>

		</div>
	</div>
</div>

<?php
require_once("facfooter.php");

?>