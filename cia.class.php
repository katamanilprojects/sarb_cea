<?php
require_once("user.class.php");
require_once("logs.class.php");

class CIA extends User
{
	private $classname = "Faculty";
	// Constructor to initialize the Faculty object
	public function __construct()
	{
		parent::__construct(); // Call the parent constructor
	}

	// Function to add internal assessment marks
	public function addInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO `internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `subjective_marks`, `objective_marks`, `assignment_marks`) VALUES (?, ?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert internal assessment marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiiddd", $studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get internal assessment marks
	public function isInternalAssessmentAdded($subjectId, $assessmentNumber)
	{
		$res = 0;
		$myname = $this->classname . " - isInternalAssessmentAdded - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `subjective_marks`, `objective_marks`, `assignment_marks` FROM `internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $subjective_marks, $objective_marks, $assignment_marks);
				while ($stmt->fetch()) {
					$res = 1;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to update internal assessment marks
	public function updateInternalAssessmentMarks($marksId, $subjectiveMarks, $objectiveMarks, $assignmentMarks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - updateInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("UPDATE `internal_assessment_marks` SET `subjective_marks` = ?, `objective_marks` = ?, `assignment_marks` = ? WHERE `id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare update internal assessment marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("dddi", $subjectiveMarks, $objectiveMarks, $assignmentMarks, $marksId);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks updated successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	public function deleteStudentMarksByComponent($componentId)
	{
		$res = ['status' => 0, 'deleted_rows' => 0];
		$myname = $this->classname . " - deleteStudentMarksByComponent - ";

		try {
			$questionIds = [];
			$questionStmt = $this->conn->prepare("SELECT `id` FROM `assessment_questions` WHERE `component_id` = ?");
			if (!$questionStmt) {
				throw new Exception("Failed to prepare get questions statement: " . $this->conn->error);
			}

			$questionStmt->bind_param("i", $componentId);
			$questionStmt->execute();
			$result = $questionStmt->get_result();
			while ($row = $result->fetch_assoc()) {
				$questionIds[] = intval($row['id']);
			}
			$questionStmt->close();

			if (empty($questionIds)) {
				$res['status'] = 1;
				return $res;
			}

			$placeholders = implode(',', array_fill(0, count($questionIds), '?'));
			$types = str_repeat('i', count($questionIds));
			$sql = "DELETE FROM `student_marks` WHERE `question_id` IN ($placeholders)";
			$stmt = $this->conn->prepare($sql);
			if (!$stmt) {
				throw new Exception("Failed to prepare delete student marks statement: " . $this->conn->error);
			}

			$stmt->bind_param($types, ...$questionIds);
			if ($stmt->execute()) {
				$res['status'] = 1;
				$res['deleted_rows'] = $stmt->affected_rows;
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	public function deleteQuestionMetadataByComponent($componentId)
	{
		$res = ['status' => 0, 'err' => ''];
		$myname = $this->classname . " - deleteQuestionMetadataByComponent - ";

		try {
			$marksCountStmt = $this->conn->prepare("SELECT COUNT(id) FROM `student_marks` where question_id in (SELECT id FROM `assessment_questions` WHERE `component_id` = ?)");
			if (!$marksCountStmt) {
				throw new Exception("Failed to prepare marks count statement: " . $this->conn->error);
			}
			$marksCountStmt->bind_param("i", $componentId);
			$marksCountStmt->execute();
			$marksCountStmt->bind_result($marksCount);
			$marksCountStmt->fetch();
			$marksCountStmt->close();

			if (!empty($marksCount)) {
				$res['err'] = 'Student marks are present for this component. Delete marks first.';
				return $res;
			}

			$this->conn->begin_transaction();

			$deleteQcoStmt = $this->conn->prepare("DELETE FROM `question_co_mapping` where question_id in (SELECT id FROM `assessment_questions` WHERE `component_id` = ?)");
			if (!$deleteQcoStmt) {
				throw new Exception("Failed to prepare delete question COs statement: " . $this->conn->error);
			}
			$deleteQcoStmt->bind_param("i", $componentId);
			$deleteQcoStmt->execute();
			$deleteQcoStmt->close();

			$deleteQuestionsStmt = $this->conn->prepare("DELETE FROM `assessment_questions` WHERE `component_id` = ?");
			if (!$deleteQuestionsStmt) {
				throw new Exception("Failed to prepare delete questions statement: " . $this->conn->error);
			}
			$deleteQuestionsStmt->bind_param("i", $componentId);
			$deleteQuestionsStmt->execute();
			$deleteQuestionsStmt->close();

			$this->conn->commit();
			$res['status'] = 1;
		} catch (Exception $e) {
			$this->conn->rollback();
			$res['err'] = 'Failed to delete question paper meta data...';
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to add internal assessment marks
	public function addPGInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addPGInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO `pg_internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `marks`) VALUES (?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert internal assessment marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiid", $studentId, $subjectId, $assessmentNumber, $marks);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get internal assessment marks
	public function isPGInternalAssessmentAdded($subjectId, $assessmentNumber)
	{
		$res = 0;
		$myname = $this->classname . " - isPGInternalAssessmentAdded - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `marks` FROM `pg_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $marks);
				while ($stmt->fetch()) {
					$res = 1;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to update internal assessment marks
	public function updatePGInternalAssessmentMarks($marksId, $marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - updatePGInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("UPDATE `pg_internal_assessment_marks` SET `marks` = ? WHERE `id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare update internal assessment marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("di", $marks, $marksId);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks updated successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to save temporary marks for internal assessments
	public function saveTempMarks($studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - saveTempMarks - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO temp_internal_assessment_marks (student_id, subject_id, assessment_number, subjective_marks, objective_marks, assignment_marks) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE subjective_marks = ?, objective_marks = ?, assignment_marks = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare save temporary marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiissssss", $studentId, $subjectId, $assessmentNumber, $subjectiveMarks, $objectiveMarks, $assignmentMarks, $subjectiveMarks, $objectiveMarks, $assignmentMarks);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks saved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to delete temporary marks
	public function deleteTempMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - deleteTempMarks - ";

		try {
			$stmt = $this->conn->prepare("DELETE FROM `temp_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare delete temporary marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks deleted successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get temporary marks
	public function getTempMarks($subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getTempMarks - ";

		try {
			$stmt = $this->conn->prepare("SELECT `student_id`, `subjective_marks`, `objective_marks`, `assignment_marks` FROM `temp_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get temporary marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($student_id, $subjective_marks, $objective_marks, $assignment_marks);
				while ($stmt->fetch()) {
					$res['data'][$student_id] = [
						'subjective_marks' => $subjective_marks,
						'objective_marks' => $objective_marks,
						'assignment_marks' => $assignment_marks
					];
				}
				$res['status'] = 1; // Temporary marks retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res['data'];
	}

	// Function to save temporary marks for internal assessments
	public function savePGTempMarks($studentId, $subjectId, $assessmentNumber, $marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - savePGTempMarks - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO temp_pg_internal_assessment_marks (student_id, subject_id, assessment_number, marks) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE marks = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare save temporary marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiiss", $studentId, $subjectId, $assessmentNumber, $marks, $marks);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks saved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to delete temporary marks
	public function deletePGTempMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - deletePGTempMarks - ";

		try {
			$stmt = $this->conn->prepare("DELETE FROM `temp_pg_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare delete temporary marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks deleted successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get temporary marks
	public function getPGTempMarks($subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getPGTempMarks - ";

		try {
			$stmt = $this->conn->prepare("SELECT `student_id`, `marks` FROM `temp_pg_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get temporary marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($student_id, $marks);
				while ($stmt->fetch()) {
					$res['data'][$student_id] = [
						'marks' => $marks
					];
				}
				$res['status'] = 1; // Temporary marks retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res['data'];
	}


	/* UG Lab CIA */

	// Function to add internal assessment marks
	public function addUGLabInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addUGLabInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO `uglab_internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `day_to_day_marks`, `internal_test_marks`) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert internal assessment marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiidd", $studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to get internal assessment marks
	public function isUGLabInternalAssessmentAdded($subjectId, $assessmentNumber)
	{
		$res = 0;
		$myname = $this->classname . " - isUGLabInternalAssessmentAdded - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `day_to_day_marks`, `internal_test_marks` FROM `uglab_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $day_to_day_marks, $internal_test_marks);
				while ($stmt->fetch()) {
					$res = 1;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	// Function to update internal assessment marks
	public function updateUGLabInternalAssessmentMarks($marksId, $day_to_day_marks, $internal_test_marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - updateUGLabInternalAssessmentMarks - ";

		try {
			$stmt = $this->conn->prepare("UPDATE `uglab_internal_assessment_marks` SET `day_to_day_marks` = ?, `internal_test_marks` = ? WHERE `id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare update internal assessment marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("ddi", $day_to_day_marks, $internal_test_marks, $marksId);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks updated successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to save temporary marks for internal assessments
	public function saveUGLabTempMarks($studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - saveUGLabTempMarks - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO temp_uglab_internal_assessment_marks (student_id, subject_id, assessment_number, day_to_day_marks, internal_test_marks) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE day_to_day_marks = ?, internal_test_marks = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare save temporary marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiissss", $studentId, $subjectId, $assessmentNumber, $day_to_day_marks, $internal_test_marks, $day_to_day_marks, $internal_test_marks);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks saved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to delete temporary marks
	public function deleteUGLabTempMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - deleteUGLabTempMarks - ";

		try {
			$stmt = $this->conn->prepare("DELETE FROM `temp_uglab_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare delete temporary marks statement: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks deleted successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get temporary marks
	public function getUGLabTempMarks($subjectId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getUGLabTempMarks - ";

		try {
			$stmt = $this->conn->prepare("SELECT `student_id`, `day_to_day_marks`, `internal_test_marks` FROM `temp_uglab_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get temporary marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($student_id, $day_to_day_marks, $internal_test_marks);
				while ($stmt->fetch()) {
					$res['data'][$student_id] = [
						'day_to_day_marks' => $day_to_day_marks,
						'internal_test_marks' => $internal_test_marks
					];
				}
				$res['status'] = 1; // Temporary marks retrieved successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res['data'];
	}

	// Function to get Course Outcomes (COs) by subject ID
	public function getCOsBySubjectId($sub_id)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getCOsBySubjectId - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `co_number`, `co_description` FROM `course_outcomes` WHERE `sub_id` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare getCOsBySubjectId statement: " . $this->conn->error);
			}

			$stmt->bind_param("i", $sub_id);
			if ($stmt->execute()) {
				$result = $stmt->get_result();
				$res['data'] = $result->fetch_all(MYSQLI_ASSOC);
				$res['status'] = 1;
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to add a new Course Outcome (CO)
	public function addCO($sub_id, $co_number, $co_description)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addCO - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO `course_outcomes` (`sub_id`, `co_number`, `co_description`) VALUES (?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare addCO statement: " . $this->conn->error);
			}

			$stmt->bind_param("iis", $sub_id, $co_number, $co_description);
			if ($stmt->execute()) {
				$res['status'] = 1; // CO added successfully
				$res['insert_id'] = $this->conn->insert_id;
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get assessment components based on assessment ID
	public function getAssessmentComponents($subId, $assessmentNumber)
	{
		$res = ['status' => 0, 'data' => []];
		$myname = $this->classname . " - getAssessmentComponents - ";

		try {
			$stmt = $this->conn->prepare("SELECT id, component_type, sequence_number 
                                      FROM assessment_components 
                                      WHERE assessment_id IN 
                                            (SELECT id FROM internal_assessments 
                                             WHERE sub_id = ? AND assessment_number = ?)
                                      ORDER BY component_type, sequence_number");
			if (!$stmt) {
				throw new Exception("Failed to prepare statement: " . $this->conn->error);
			}

			$stmt->bind_param("is", $subId, $assessmentNumber);
			if ($stmt->execute()) {
				$result = $stmt->get_result();
				$res['data'] = $result->fetch_all(MYSQLI_ASSOC);
				$res['status'] = 1; // Success
			} else {
				$this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
			}

			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	
	// Function to add an assessment component, ensuring the internal assessment exists
	public function addAssessmentComponent($subId, $assessmentNumber, $componentType)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addAssessmentComponent - ";

		try {
			// Fetch assessment ID
			$stmt = $this->conn->prepare("SELECT id FROM internal_assessments WHERE sub_id = ? AND assessment_number = ?");
			$stmt->bind_param("is", $subId, $assessmentNumber);
			$stmt->execute();
			$stmt->bind_result($assessmentId);
			$stmt->fetch();
			$stmt->close();
	
			if(empty($assessmentId)){
				$assessmentId = $this->addInternalAssessments($subId, $assessmentNumber);
			}

			// Determine sequence number
			if ($componentType === "Activity" || $componentType === "Assignment" || $componentType === "Day-to-Day") {
				$stmt = $this->conn->prepare("SELECT MAX(sequence_number) FROM assessment_components WHERE assessment_id = ? AND component_type = ?");
				$stmt->bind_param("is", $assessmentId, $componentType );
				$stmt->execute();
				$stmt->bind_result($maxSequence);
				$stmt->fetch();
				$stmt->close();
				$sequenceNumber = ($maxSequence !== null) ? $maxSequence + 1 : 1;
			} else {
				$sequenceNumber = 1; // Always 1 for Subjective/Objective
			}
	
			// Insert component
			$stmt = $this->conn->prepare("INSERT INTO assessment_components (assessment_id, component_type, sequence_number) VALUES (?, ?, ?)");
			$stmt->bind_param("isi", $assessmentId, $componentType, $sequenceNumber);
			if ($stmt->execute()) {
				$res['status'] = 1;
				$res['comp_id'] = $this->conn->insert_id;
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog("addAssessmentComponent Exception: " . $e->getMessage());
		}

		return $res;
	}

	public function getSequenceNoByAssessCompID($componentId)
	{
		$res = 0;
		$myname = $this->classname . " - getSequenceNoByAssessCompID - ";

		try {
			$stmt = $this->conn->prepare("SELECT sequence_number FROM assessment_components WHERE id=?");
			if (!$stmt) {
				throw new Exception("Failed to prepare statement: " . $this->conn->error);
			}

			$stmt->bind_param("i", $componentId);
			if ($stmt->execute()) {
				$stmt->bind_result($sequenceNumber);
				$stmt->fetch();
				$res = $sequenceNumber;
			} else {
				$this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
			}

			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	// Function to get Bloom's Taxonomy Levels
	public function getBloomsLevels()
	{
		$res = [];
		$myname = $this->classname . " - getBloomsLevels - ";

		try {
			$stmt = $this->conn->prepare("SELECT id, blooms_level, blooms_label FROM blooms_levels ORDER BY id ASC");
			if (!$stmt) {
				throw new Exception("Failed to prepare get Blooms Levels statement: " . $this->conn->error);
			}

			if ($stmt->execute()) {
				$result = $stmt->get_result();
				while ($row = $result->fetch_assoc()) {
					$res[] = $row;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

		// Function to get Questions by Component ID
		public function getMarksByComponent($componentId)
		{
			$res = [];
			$myname = $this->classname . " - getMarksByComponent - ";
	
			try {
				$stmt = $this->conn->prepare("SELECT id FROM student_marks WHERE question_id IN (SELECT q.id 
										  FROM assessment_questions q 
										  WHERE q.component_id = ?)
										  ");
				if (!$stmt) {
					throw new Exception("Failed to prepare get Questions by Component statement: " . $this->conn->error);
				}
	
				$stmt->bind_param("i", $componentId);
				if ($stmt->execute()) {
					$result = $stmt->get_result();
					while ($row = $result->fetch_assoc()) {
						$res[] = $row;
					}
				} else {
					$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
				}
				$stmt->close();
			} catch (Exception $e) {
				$this->logs->errLog($myname . "Exception: " . $e->getMessage());
			}
	
			return $res;
		}

	// Function to get Questions by Component ID
	public function getQuestionsByComponent($componentId)
	{
		$res = [];
		$myname = $this->classname . " - getQuestionsByComponent - ";

		try {
			$stmt = $this->conn->prepare("SELECT q.id, q.question_label, q.question_type, q.marks, q.blooms_level_id 
									  FROM assessment_questions q 
									  WHERE q.component_id = ?
									  ORDER BY q.id ASC");
			if (!$stmt) {
				throw new Exception("Failed to prepare get Questions by Component statement: " . $this->conn->error);
			}

			$stmt->bind_param("i", $componentId);
			if ($stmt->execute()) {
				$result = $stmt->get_result();
				while ($row = $result->fetch_assoc()) {
					$res[] = $row;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}

		return $res;
	}

	public function getCOsByQuestion($question_id)
	{
		$cos = [];
		$stmt = $this->conn->prepare("SELECT c.co_number 
                                  FROM question_co_mapping aqc 
                                  JOIN course_outcomes c ON aqc.co_id = c.id 
                                  WHERE aqc.question_id = ?");
		$stmt->bind_param("i", $question_id);
		$stmt->execute();
		$result = $stmt->get_result();

		while ($row = $result->fetch_assoc()) {
			$cos[] = "CO" . $row['co_number'];
		}

		return $cos; // Returns an array of CO labels associated with the question
	}

	public function addQuestionCOs($question_id, $co_ids)
	{
		$myname = $this->classname . " - addQuestionCOs - ";

		try {

			$stmt = $this->conn->prepare("INSERT INTO question_co_mapping (question_id, co_id) VALUES (?, ?)");

			foreach ($co_ids as $co_id) {
				$stmt->bind_param("ii", $question_id, $co_id);
				$stmt->execute();
			}

			return true;
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
	}

	public function addQuestion($componentid, $label, $type, $marks, $blooms_level)
	{
		$myname = $this->classname . " - addQuestion - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO assessment_questions (component_id, question_label, question_type, marks, blooms_level_id) 
                                  VALUES (?, ?, ?, ?, ?)");
			$stmt->bind_param("issdi", $componentid, $label, $type, $marks, $blooms_level);
			$stmt->execute();
			return $this->conn->insert_id; // Returns the inserted question ID
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
	}

	public function getQuestionDetails($question_id)
	{
		$stmt = $this->conn->prepare("SELECT marks FROM assessment_questions WHERE id = ?");
		$stmt->bind_param("i", $question_id);
		$stmt->execute();
		$result = $stmt->get_result();
		return $result->fetch_assoc();
	}

	public function getStudentMarksByStId($stu_id)
	{
		$myname = $this->classname . " - getStudentMarksByStId - ";

		$res = array();

		try {
			// Check if the record already exists
			$stmt = $this->conn->prepare("SELECT question_id, id, marks_obtained FROM student_marks WHERE stu_id = ?");
			$stmt->bind_param("i", $stu_id);
			$stmt->execute();
			$stmt->bind_result($question_id, $id, $marks_obtained);
			
			while($stmt->fetch()){
				$res[$question_id] = $marks_obtained;
			}
			return $res;
			
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
	}


	public function checkStudentMarks($stu_id, $question_id)
	{
		$myname = $this->classname . " - checkStudentMarks - ";

		try {
			// Check if the record already exists
			$stmt = $this->conn->prepare("SELECT id FROM student_marks WHERE stu_id = ? AND question_id = ?");
			$stmt->bind_param("ii", $stu_id, $question_id);
			$stmt->execute();
			$result = $stmt->get_result();

			if ($result->num_rows > 0) {
				return $result->fetch_assoc();
			}
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
	}

	public function addOrUpdateStudentMarks($stu_id, $question_id, $marks_obtained)
	{
		// Check if the record already exists
		$stmt = $this->conn->prepare("SELECT id FROM student_marks WHERE stu_id = ? AND question_id = ?");
		$stmt->bind_param("ii", $stu_id, $question_id);
		$stmt->execute();
		$result = $stmt->get_result();

		if ($result->num_rows > 0) {
			// Update existing record
			$stmt = $this->conn->prepare("UPDATE student_marks SET marks_obtained = ? WHERE stu_id = ? AND question_id = ?");
			$stmt->bind_param("dii", $marks_obtained, $stu_id, $question_id);
		} else {
			// Insert new record
			$stmt = $this->conn->prepare("INSERT INTO student_marks (stu_id, question_id, marks_obtained) VALUES (?, ?, ?)");
			$stmt->bind_param("iid", $stu_id, $question_id, $marks_obtained);
		}
		return $stmt->execute();
	}

	public function getMarksByType($student_id, $sub_id, $assessment_number, $component_type) {
		$total_marks = 0; // Default to 0 if no marks exist
	
		// Subjective should be handled separately using getSubjectiveQuestionMarks()
		if ($component_type === "Subjective") {
			return 0; 
		}
	
		$query = "SELECT SUM(sm.marks_obtained) AS total_marks
				  FROM student_marks sm
				  INNER JOIN assessment_questions aq ON sm.question_id = aq.id
				  INNER JOIN assessment_components ac ON aq.component_id = ac.id
				  INNER JOIN internal_assessments ia ON ac.assessment_id = ia.id
				  WHERE sm.stu_id = ? 
				  AND ia.sub_id = ? 
				  AND ia.assessment_number = ? 
				  AND ac.component_type = ?";
	
		if ($stmt = $this->conn->prepare($query)) {
			$stmt->bind_param("iiis", $student_id, $sub_id, $assessment_number, $component_type);
			$stmt->execute();
			$stmt->bind_result($total_marks);
			$stmt->fetch();
			$stmt->close();
		}
	
		return (float) $total_marks; // Ensure numeric return value
	}

	public function getSubjectiveQuestionMarks($student_id, $sub_id, $assessment_number) {
		$subjectiveMarks = []; // Initialize array properly
	
		$query = "SELECT aq.question_label, sm.marks_obtained 
				  FROM student_marks sm
				  INNER JOIN assessment_questions aq ON sm.question_id = aq.id
				  INNER JOIN assessment_components ac ON aq.component_id = ac.id
				  INNER JOIN internal_assessments ia ON ac.assessment_id = ia.id
				  WHERE sm.stu_id = ? 
				  AND ia.sub_id = ? 
				  AND ia.assessment_number = ? 
				  AND ac.component_type = 'Subjective'";
	
		if ($stmt = $this->conn->prepare($query)) {
			$stmt->bind_param("iii", $student_id, $sub_id, $assessment_number);
			$stmt->execute();
			$result = $stmt->get_result();
	
			while ($row = $result->fetch_assoc()) {
				$questionLabel = $row['question_label'];  // Example: "1", "2", "3", "4"
				$marksObtained = (float) $row['marks_obtained'];
	
				// Extract main question number (e.g., "1" from "1a")
				$mainQuestionNum = preg_replace('/[^0-9]/', '', $questionLabel);
	
				// Ensure key exists for either-or pairs (1-2, 3-4, 5-6)
				if (!isset($subjectiveMarks[$mainQuestionNum])) {
					$subjectiveMarks[$mainQuestionNum] = 0;
				}
	
				// Store the highest marks for either-or pair
				// $subjectiveMarks[$mainQuestionNum] = max($subjectiveMarks[$mainQuestionNum], $marksObtained);
				$subjectiveMarks[$mainQuestionNum] += $marksObtained;

			}
	
			$stmt->close();
		}
	
		return $subjectiveMarks; // Ensure it returns the processed array
	}

	public function getTotalMarksByType($sub_id, $assessment_number, $component_type) {
		$total_marks = 0;
	
		$query = "SELECT SUM(aq.marks) AS full_marks
				  FROM assessment_questions aq
				  INNER JOIN assessment_components ac ON aq.component_id = ac.id
				  INNER JOIN internal_assessments ia ON ac.assessment_id = ia.id
				  WHERE ia.sub_id = ? 
				  AND ia.assessment_number = ? 
				  AND ac.component_type = ?";
	
		if ($stmt = $this->conn->prepare($query)) {
			$stmt->bind_param("iis", $sub_id, $assessment_number, $component_type);
			$stmt->execute();
			$stmt->bind_result($full_marks);
			$stmt->fetch();
			$stmt->close();
		}
	
		return $full_marks ?? 0; // Return full marks or 0 if no questions exist
	}

	public function addInternalAssessments($sub_id, $assessment_number)
	{
		$myname = $this->classname . " - addInternalAssessments - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO `internal_assessments`(`sub_id`, `assessment_number`) VALUES (?, ?)");
			$stmt->bind_param("is", $sub_id, $assessment_number);
			$stmt->execute();
			return $this->conn->insert_id; // Returns the inserted ID
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
	}

	public function getRelevantPoPso($sub_id)
    {
        $res = ['status' => 0, 'data' => []];
        $myname = $this->classname . " - getRelevantPoPso - ";
        $spec_id = null;
        $regulation = null;

        try {
            // Query to get spec_id AND reg from sub_id (subjects -> classes)
            $query_class_details = "SELECT c.spec_id, c.reg, c.acad_year
                                    FROM subjects s
                                    JOIN classes c ON s.class_id = c.id
                                    WHERE s.id = ?";
            $stmt1 = $this->conn->prepare($query_class_details);
            if (!$stmt1) { throw new Exception("Prepare failed (stmt1): " . $this->conn->error); }
            $stmt1->bind_param("i", $sub_id);
            if (!$stmt1->execute()) { throw new Exception("Execute failed (stmt1): " . $stmt1->error); }
            $stmt1->bind_result($spec_id, $regulation, $acad_year);
            $details_found = $stmt1->fetch();
            $stmt1->close();

            if ($details_found && $spec_id && $regulation) {
                // Fetch POs/PSOs for that spec_id AND regulation
                // ORDER BY 'po_pso' column first (assuming 'PO' comes before 'PSO' alphabetically), then orderid
                $query_pops = "SELECT `id`, `code`, `description`, `po_pso`
                               FROM `po_pso`
                               WHERE `acad_year` = ? AND `specid` = ? AND `regulation` = ?
                               ORDER BY `po_pso` ASC, `orderid` ASC, `id` ASC"; // Added ORDER BY po_pso
                $stmt2 = $this->conn->prepare($query_pops);
                if (!$stmt2) { throw new Exception("Prepare failed (stmt2): " . $this->conn->error); }
                $stmt2->bind_param("sis", $acad_year, $spec_id, $regulation);
                if ($stmt2->execute()) {
                    $result = $stmt2->get_result();
                    $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
                    // Check if data was actually fetched before setting status to 1
                    if (!empty($res['data'])) {
                        $res['status'] = 1;
                    } else {
                        $this->logs->warningLog($myname . "No POs/PSOs found for spec ID: $spec_id and regulation: $regulation");
                        // Optionally keep status 0 or set to 1 with empty data
                        $res['status'] = 1; // Set status 1 even if empty, so the page knows the query ran
                    }
                } else { $this->logs->errLog($myname . "Execute failed (stmt2): " . $stmt2->error); }
                $stmt2->close();
            } else { $this->logs->warningLog($myname . "Could not find spec ID/regulation for subject ID: $sub_id"); }

        } catch (Exception $e) { $this->logs->errLog($myname . "Exception: " . $e->getMessage()); }
        return $res;
    }

    /**
     * Fetches existing CO-PO/PSO mappings and returns the weightage.
     * (Previously implemented - ensure it selects 'weightage')
     * @param array $co_ids Array of Course Outcome IDs
     * @param array $po_pso_ids Array of PO/PSO IDs
     * @return array Associative array where keys are 'co_id-po_id' and values are the weightage (1, 2, or 3)
     */
    public function getCoPoMappings($co_ids, $po_pso_ids) {
        $mappings = [];
        if (empty($co_ids) || empty($po_pso_ids)) {
            return $mappings;
        }
        $myname = $this->classname . " - getCoPoMappings - ";

        $co_placeholders = implode(',', array_fill(0, count($co_ids), '?'));
        $po_pso_placeholders = implode(',', array_fill(0, count($po_pso_ids), '?'));
        $types = str_repeat('i', count($co_ids) + count($po_pso_ids));
        $params = array_merge($co_ids, $po_pso_ids);

        try {
            // Ensure weightage is selected
            $query = "SELECT `co_id`, `po_id`, `weightage` FROM `co_po_mapping` WHERE `co_id` IN ($co_placeholders) AND `po_id` IN ($po_pso_placeholders)";
            $stmt = $this->conn->prepare($query);
            if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }

            $stmt->bind_param($types, ...$params);

            if ($stmt->execute()) {
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    // Store the actual weightage
                    if (isset($row['weightage']) && in_array($row['weightage'], [1, 2, 3])) {
                         $mappings[$row['co_id'] . '-' . $row['po_id']] = (int)$row['weightage'];
                    }
                }
            } else { $this->logs->errLog($myname . "Execute failed: " . $stmt->error); }
            $stmt->close();
        } catch (Exception $e) { $this->logs->errLog($myname . "Exception: " . $e->getMessage()); }
        return $mappings;
    }

     /**
      * Adds or updates a single CO-PO/PSO mapping with specific weightage (1, 2, or 3).
      * @param int $co_id Course Outcome ID
      * @param int $po_pso_id PO/PSO ID
      * @param int $weightage The weightage value (1, 2, or 3)
      * @return bool True on success, False on failure
      */
    public function addUpdateCoPoMapping($co_id, $po_pso_id, $weightage) {
        // Validate weightage
        if (!in_array($weightage, [1, 2, 3])) {
            $this->logs->warningLog("Invalid weightage value ($weightage) provided for CO_ID $co_id, PO_ID $po_pso_id.");
            return false;
        }

        $myname = $this->classname . " - addUpdateCoPoMapping - ";
        try {
            // Use INSERT ... ON DUPLICATE KEY UPDATE
            $stmt = $this->conn->prepare("INSERT INTO co_po_mapping (co_id, po_id, weightage) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE weightage = ?");
             if (!$stmt) { throw new Exception("Prepare failed: " . $this->conn->error); }

            // Bind the integer weightage value
            $stmt->bind_param("iiii", $co_id, $po_pso_id, $weightage, $weightage);

            if ($stmt->execute()) {
                 $stmt->close();
                return true;
            } else {
                $this->logs->errLog($myname . "Execute failed: " . $stmt->error);
                $stmt->close();
                return false;
            }
        } catch (Exception $e) {
             $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            return false;
        }
    }

	// -----------------------------------------------------------------
	// FUNCTIONS COPIED FROM faculty.class.php FOR USE IN importCiaData
	// -----------------------------------------------------------------

	/**
	 * Function to add CIA Attachment
	 * (Copied from faculty.class.php)
	 */
	public function addCIAAttachment($subjectId, $assessmentNumber, $fileTitle, $filePath)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addCIAAttachment - ";

		try {
			$stmt = $this->conn->prepare("INSERT INTO `cia_attachments`(`subject_id`, `assessment_number`, `file_title`, `file_path`) VALUES (?, ?, ?, ?)");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert cia_attachments statement: " . $this->conn->error);
			}

			$stmt->bind_param("iiss", $subjectId, $assessmentNumber, $fileTitle, $filePath);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	/**
	 * Function to get CIa Attchaments
	 * (Copied from faculty.class.php)
	 */
	public function getCIAAttachments($subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - getCIAAttachments - ";

		try {
			$stmt = $this->conn->prepare("SELECT `id`, `file_title`, `file_path` FROM `cia_attachments` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) {
				throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
			}

			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id, $fileTitle, $filePath);
				$res["count"] = 0;
				$res["files"] = []; // Initialize files array
				while ($stmt->fetch()) {
					$res['status'] = 1;
					$res['files'][$res["count"]]['id'] = $id;
					$res['files'][$res["count"]]['file_title'] = $fileTitle;
					$res['files'][$res["count"]]['file_path'] = $filePath;
					$res["count"]++;
				}
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	/**
	 * Function to delete CIA Attachment
	 * (Copied from faculty.class.php)
	 */
	public function deleteCIAAttachment($id, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - deleteCIAAttachment - ";

		try {
			$stmt = $this->conn->prepare("DELETE FROM `cia_attachments` WHERE `id`=? and `subject_id`=? and `assessment_number`=?");
			if (!$stmt) {
				throw new Exception("Failed to prepare insert cia_attachments statement: " . $this->conn->error);
			}

			$stmt->bind_param("iii", $id, $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$res['status'] = 1; // Marks added successfully
			} else {
				$this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			}
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

    /* UG Project CIA */

	public function addUGProjectInternalAssessmentMarks($studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - addUGProjectInternalAssessmentMarks - ";
		try {
			$stmt = $this->conn->prepare("INSERT INTO `ugproject_internal_assessment_marks` (`student_id`, `subject_id`, `assessment_number`, `component1_marks`, `component2_marks`) VALUES (?, ?, ?, ?, ?)");
			if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
			$stmt->bind_param("iiidd", $studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks);
			if ($stmt->execute()) $res['status'] = 1;
			else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	public function isUGProjectInternalAssessmentAdded($subjectId, $assessmentNumber)
	{
		$res = 0;
		$myname = $this->classname . " - isUGProjectInternalAssessmentAdded - ";
		try {
			$stmt = $this->conn->prepare("SELECT `id` FROM `ugproject_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ? LIMIT 1");
			if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($id);
				if ($stmt->fetch()) $res = 1;
			} else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	public function saveUGProjectTempMarks($studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - saveUGProjectTempMarks - ";
		try {
			$stmt = $this->conn->prepare("INSERT INTO temp_ugproject_internal_assessment_marks (student_id, subject_id, assessment_number, component1_marks, component2_marks) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE component1_marks = ?, component2_marks = ?");
			if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
			$stmt->bind_param("iiissss", $studentId, $subjectId, $assessmentNumber, $component1_marks, $component2_marks, $component1_marks, $component2_marks);
			if ($stmt->execute()) $res['status'] = 1;
			else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	public function deleteUGProjectTempMarks($studentId, $subjectId, $assessmentNumber)
	{
		$res = ['status' => 0];
		$myname = $this->classname . " - deleteUGProjectTempMarks - ";
		try {
			$stmt = $this->conn->prepare("DELETE FROM `temp_ugproject_internal_assessment_marks` WHERE `student_id` = ? AND `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
			$stmt->bind_param("iii", $studentId, $subjectId, $assessmentNumber);
			if ($stmt->execute()) $res['status'] = 1;
			else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

	public function getUGProjectTempMarks($subjectId, $assessmentNumber)
	{
		$res = [];
		$myname = $this->classname . " - getUGProjectTempMarks - ";
		try {
			$stmt = $this->conn->prepare("SELECT `student_id`, `component1_marks`, `component2_marks` FROM `temp_ugproject_internal_assessment_marks` WHERE `subject_id` = ? AND `assessment_number` = ?");
			if (!$stmt) throw new Exception("Failed to prepare statement: " . $this->conn->error);
			$stmt->bind_param("ii", $subjectId, $assessmentNumber);
			if ($stmt->execute()) {
				$stmt->bind_result($student_id, $component1_marks, $component2_marks);
				while ($stmt->fetch()) {
					$res[$student_id] = ['component1_marks' => $component1_marks, 'component2_marks' => $component2_marks];
				}
			} else $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
			$stmt->close();
		} catch (Exception $e) {
			$this->logs->errLog($myname . "Exception: " . $e->getMessage());
		}
		return $res;
	}

    // ---------- NEW FUNCTIONS FOR GROUPED CIA IMPORT (NON-DESTRUCTIVE) ----------

    /**
     * Helper to get or create an assessment ID. Returns null if not found and not auto-creating.
     * @param int $sub_id
     * @param string $assessment_number '1' or '2'
     * @param bool $auto_create If true, will create the internal_assessment record if not found.
     * @return int|null
     */
    public function getAssessmentId($sub_id, $assessment_number, $auto_create = false) {
        $stmt = $this->conn->prepare("SELECT id FROM internal_assessments WHERE sub_id = ? AND assessment_number = ?");
        $stmt->bind_param("is", $sub_id, $assessment_number);
        $stmt->execute();
        $stmt->bind_result($assessmentId);
        $found = $stmt->fetch();
        $stmt->close();

        if ($found) {
            return $assessmentId;
        } elseif ($auto_create) {
            return $this->addInternalAssessments($sub_id, $assessment_number); // Re-uses existing function
        }
        return null;
    }

    /**
     * Helper to get a component's ID by its attributes.
     * @param int $assessment_id
     * @param string $component_type
     * @param int $sequence_number
     * @return int|null
     */
    private function getComponentId($assessment_id, $component_type, $sequence_number) {
        $stmt = $this->conn->prepare("SELECT id FROM assessment_components WHERE assessment_id = ? AND component_type = ? AND sequence_number = ?");
        $stmt->bind_param("isi", $assessment_id, $component_type, $sequence_number);
        $stmt->execute();
        $stmt->bind_result($componentId);
        $found = $stmt->fetch();
        $stmt->close();
        return $found ? $componentId : null;
    }
    
    /**
     * Helper to get a map of CO Numbers to CO IDs for a specific subject.
     * @param int $sub_id
     * @return array [co_number => co_id]
     */
    private function getCoMapByNumber($sub_id) {
        $co_map = [];
        $stmt = $this->conn->prepare("SELECT id, co_number FROM course_outcomes WHERE sub_id = ?");
        $stmt->bind_param("i", $sub_id);
        $stmt->execute();
        $stmt->bind_result($co_id, $co_number);
        while ($stmt->fetch()) {
            $co_map[$co_number] = $co_id;
        }
        $stmt->close();
        return $co_map;
    }
    
    /**
     * Helper to get the CO numbers (e.g., [1, 3]) for a source question.
     * @param int $question_id
     * @return array
     */
    private function getSourceQuestionCoNumbers($question_id) {
        $co_numbers = [];
        $stmt = $this->conn->prepare("SELECT co.co_number FROM question_co_mapping qcm JOIN course_outcomes co ON qcm.co_id = co.id WHERE qcm.question_id = ?");
        $stmt->bind_param("i", $question_id);
        $stmt->execute();
        $stmt->bind_result($co_number);
        while ($stmt->fetch()) {
            $co_numbers[] = $co_number;
        }
        $stmt->close();
        return $co_numbers;
    }
    
    /**
     * Helper to get public-facing CIA attachments (non-destructive import needs this)
     * @param int $subjectId
     * @param string $assessmentNumber
     * @return array
     */
    public function getPublicCIAAttachments($subjectId, $assessmentNumber){
        $res = ['status' => 0, 'files' => []]; // Ensure 'files' is an array
        $myname = $this->classname . " - getPublicCIAAttachments - ";

        try {
            $stmt = $this->conn->prepare("SELECT `id`, `file_title`, `file_path` FROM `cia_attachments` WHERE `subject_id` = ? AND `assessment_number` = ?");
            if (!$stmt) {
                throw new Exception("Failed to prepare get internal assessment marks query: " . $this->conn->error);
            }

            $stmt->bind_param("is", $subjectId, $assessmentNumber); // Use 'is'
            if ($stmt->execute()) {
                $stmt->bind_result($id, $fileTitle, $filePath);
                $res["count"] = 0;
                while ($stmt->fetch()) {
                    $res['status'] = 1;
                    $res['files'][] = [ // Directly append to files
                        'id' => $id,
                        'file_title' => $fileTitle,
                        'file_path' => $filePath
                    ];
                    $res["count"]++;
                }
            } else {
                $this->logs->errLog($myname . "Statement not executed: " . $this->conn->error);
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }
    
    /**
     * Main function to import CIA data from a source subject to a target subject. (NON-DESTRUCTIVE)
     * @param int $source_sub_id - The subject ID to copy FROM.
     * @param int $target_sub_id - The subject ID to copy TO.
     * @param string $assessment_number - '1' or '2'.
     * @param array $components_to_copy - Array of keys, e.g., ["Subjective:1", "Assignment:2"].
     * @param array $attachments_to_copy - Array of file titles, e.g., ["CIA1_QP.pdf"].
     * @return array ['status' => 1] or ['status' => 0, 'error' => '...']
     */
    public function importCiaData($source_sub_id, $target_sub_id, $assessment_number, $components_to_copy, $attachments_to_copy) {
        $myname = $this->classname . " - importCiaData (Non-Destructive) - ";
        $res = ['status' => 0, 'error' => ''];

        try {
            $this->conn->begin_transaction();

            // Get source assessment ID
            $source_assessment_id = $this->getAssessmentId($source_sub_id, $assessment_number);
            if (!$source_assessment_id) {
                throw new Exception("Source subject (ID: $source_sub_id) has no CIA setup for assessment $assessment_number.");
            }

            // Get or create target assessment ID
            $target_assessment_id = $this->getAssessmentId($target_sub_id, $assessment_number, true);
            if (!$target_assessment_id) {
                 throw new Exception("Could not get or create target assessment for subject ID $target_sub_id.");
            }

            // Get target CO map (CO_Number -> CO_ID)
            $target_co_map = $this->getCoMapByNumber($target_sub_id);
            if (empty($target_co_map)) {
                $this->logs->warningLog($myname . "Target subject (ID: $target_sub_id) has no COs defined. Questions will be copied without CO mappings.");
            }

            // --- 1. Handle Metadata Copy ---
            if (!empty($components_to_copy)) {
                $stmt_c = $this->conn->prepare("INSERT INTO assessment_components (assessment_id, component_type, sequence_number) VALUES (?, ?, ?)");
                $stmt_q = $this->conn->prepare("INSERT INTO assessment_questions (component_id, question_label, question_type, marks, blooms_level_id) VALUES (?, ?, ?, ?, ?)");
                $stmt_map = $this->conn->prepare("INSERT INTO question_co_mapping (question_id, co_id) VALUES (?, ?)");

                foreach ($components_to_copy as $comp_key) {
                    list($comp_type, $comp_seq) = explode(':', $comp_key);

                    // A. Check if target *already* has this component AND if it has questions
                    $target_component_id = $this->getComponentId($target_assessment_id, $comp_type, $comp_seq);
                    
                    if ($target_component_id) {
                        $existing_questions = $this->getQuestionsByComponent($target_component_id);
                        if (!empty($existing_questions)) {
                            $this->logs->warningLog($myname . "Skipping import for component $comp_key on target sub $target_sub_id: Component already has metadata (questions).");
                            continue; // NON-DESTRUCTIVE: Skip if target already has questions
                        }
                    } else {
                        // B. Create new component for target if it doesn't exist at all
                        $stmt_c->bind_param("isi", $target_assessment_id, $comp_type, $comp_seq);
                        if (!$stmt_c->execute()) throw new Exception("Failed to create target component $comp_key.");
                        $target_component_id = $this->conn->insert_id;
                    }

                    // C. Get the source component ID
                    $source_component_id = $this->getComponentId($source_assessment_id, $comp_type, $comp_seq);
                    if (!$source_component_id) {
                         $this->logs->errLog($myname . "Could not find source component $comp_key for source subject $source_sub_id. Skipping.");
                         continue;
                    }

                    // D. Get and copy source questions
                    $source_questions = $this->getQuestionsByComponent($source_component_id);
                    if (empty($source_questions)) {
                        $this->logs->warningLog($myname . "Source component $comp_key for source subject $source_sub_id has no questions. Nothing to copy.");
                        continue;
                    }
                    
                    foreach ($source_questions as $src_q) {
                        $stmt_q->bind_param("issdi", $target_component_id, $src_q['question_label'], $src_q['question_type'], $src_q['marks'], $src_q['blooms_level_id']);
                        if (!$stmt_q->execute()) throw new Exception("Failed to create target question " . $src_q['question_label']);
                        $new_question_id = $this->conn->insert_id;

                        // E. Get and copy CO mappings
                        $source_co_numbers = $this->getSourceQuestionCoNumbers($src_q['id']);
                        foreach ($source_co_numbers as $co_num) {
                            $co_num_int = (int) $co_num; // Use the number
                            if (isset($target_co_map[$co_num_int])) {
                                $target_co_id = $target_co_map[$co_num_int];
                                $stmt_map->bind_param("ii", $new_question_id, $target_co_id);
                                if (!$stmt_map->execute()) throw new Exception("Failed to create CO mapping for new QID $new_question_id.");
                            } else {
                                $this->logs->warningLog($myname . "CO number $co_num not found for target subject $target_sub_id. Skipping mapping for question $new_question_id.");
                            }
                        }
                    }
                }
                $stmt_c->close();
                $stmt_q->close();
                $stmt_map->close();
            }

            // --- 2. Handle Attachment Copy ---
            if (!empty($attachments_to_copy)) {
                $stmt_get_src_att = $this->conn->prepare("SELECT file_path FROM cia_attachments WHERE subject_id = ? AND assessment_number = ? AND file_title = ?");
                $stmt_check_att = $this->conn->prepare("SELECT id FROM cia_attachments WHERE subject_id = ? AND assessment_number = ? AND file_title = ?");
                $stmt_a = $this->conn->prepare("INSERT INTO cia_attachments (subject_id, assessment_number, file_title, file_path) VALUES (?, ?, ?, ?)");

                foreach ($attachments_to_copy as $file_title) {
                    // A. Check if target *already* has this attachment
                    $stmt_check_att->bind_param("iss", $target_sub_id, $assessment_number, $file_title);
                    $stmt_check_att->execute();
                    $stmt_check_att->store_result();
                    if ($stmt_check_att->num_rows > 0) {
                        $this->logs->warningLog($myname . "Skipping import for attachment '$file_title' on target sub $target_sub_id: Attachment already exists.");
                        continue;
                    }

                    // B. Get the source attachment file_path
                    $stmt_get_src_att->bind_param("iss", $source_sub_id, $assessment_number, $file_title);
                    $stmt_get_src_att->execute();
                    $stmt_get_src_att->bind_result($file_path);
                    $found_attachment = $stmt_get_src_att->fetch();
                    $stmt_get_src_att->free_result();
                    
                    if (!$found_attachment) {
                        $this->logs->errLog($myname . "Could not find source attachment '$file_title' for source subject $source_sub_id. Skipping.");
                        continue;
                    }

                    // C. Insert new attachment for target
                    $stmt_a->bind_param("isss", $target_sub_id, $assessment_number, $file_title, $file_path);
                    if (!$stmt_a->execute()) throw new Exception("Failed to copy attachment reference for '$file_title'.");
                }
                $stmt_get_src_att->close();
                $stmt_check_att->close();
                $stmt_a->close();
            }

            // --- 3. Commit ---
            $this->conn->commit();
            $res['status'] = 1;

        } catch (Exception $e) {
            $this->conn->rollback();
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
            $res['error'] = $e->getMessage();
        }

        return $res;
    }

    /**
     * Look up the active regulation code (e.g. 'R23', 'R20', 'R19') for a subject ID.
     */
    public function getRegulationForSubject($sub_id): string
    {
        $reg = 'R23';
        $subId = (int)$sub_id;
        if ($subId <= 0) {
            return $reg;
        }

        try {
            $stmt = $this->conn->prepare("
                SELECT c.reg 
                FROM subjects s 
                JOIN classes c ON s.class_id = c.id 
                WHERE s.id = ?
            ");
            if ($stmt) {
                $stmt->bind_param("i", $subId);
                if ($stmt->execute()) {
                    $stmt->bind_result($found_reg);
                    if ($stmt->fetch() && !empty($found_reg)) {
                        $reg = strtoupper(trim($found_reg));
                    }
                }
                $stmt->close();
            }
        } catch (Exception $e) {
            $this->logs->errLog("CIA - getRegulationForSubject Exception: " . $e->getMessage());
        }
        return $reg;
    }

    /**
     * Retrieve academic settings instance or specific key dynamically.
     */
    public function getAcademicSetting(string $key, ?string $regulation = null, ?int $sub_id = null, mixed $fallback = null): mixed
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        return \Services\SettingsService::getInstance()->get($key, $regulation ?? 'R23', $fallback);
    }

    /**
     * Calculate continuous internal assessment (CIA) marks using regulation weights.
     * Replaces hardcoded 0.8/0.2 formula.
     */
    public function calculateCiaFinalMarks(float $mid1, float $mid2, ?string $regulation = null, ?int $sub_id = null): array
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        return \Services\SettingsService::getInstance()->calculateCiaFinal($mid1, $mid2, $regulation ?? 'R23');
    }

    /**
     * Dynamic subjective marks condensation.
     * Replaces hardcoded (total / 30) * 15 with dynamic settings.
     */
    public function condenseSubjectiveMarks(float $rawMarks, float $fullMarks = 30.0, ?string $regulation = null, ?int $sub_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        $condensedTarget = (float)$this->getAcademicSetting('theory_mid_subjective_condensed', $regulation, $sub_id, 15.0);
        $full = max(1.0, $fullMarks);
        return round(min(($rawMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

    /**
     * Dynamic objective marks condensation.
     */
    public function condenseObjectiveMarks(float $rawMarks, float $fullMarks = 10.0, ?string $regulation = null, ?int $sub_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        $condensedTarget = (float)$this->getAcademicSetting('theory_mid_objective_marks', $regulation, $sub_id, 10.0);
        $full = max(1.0, $fullMarks);
        return round(min(($rawMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

    /**
     * Dynamic assignment marks condensation.
     */
    public function condenseAssignmentMarks(float $rawMarks, float $fullMarks = 5.0, ?string $regulation = null, ?int $sub_id = null): float
    {
        require_once __DIR__ . '/services/SettingsService.php';
        if (empty($regulation) && !empty($sub_id)) {
            $regulation = $this->getRegulationForSubject($sub_id);
        }
        $condensedTarget = (float)$this->getAcademicSetting('theory_assignment_marks', $regulation, $sub_id, 5.0);
        $full = max(1.0, $fullMarks);
        return round(min(($rawMarks / $full) * $condensedTarget, $condensedTarget), 2);
    }

}