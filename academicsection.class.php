<?php
require_once("user.class.php");

class AcademicSection extends User
{
    private $classname = "AcademicSection";

    public function __construct()
    {
        parent::__construct();
    }

    public function updatePwd($userId, $oldPassword, $newPassword)
    {
        $res = ['status' => 0];
        if ($this->verifyPwd($userId, $oldPassword)) {
            $res = $this->updatePassword($userId, $newPassword, "academic_section");
        } else {
            $res['err'] = "Incorrect Existing Password";
        }
        return $res;
    }

    /**
     * Get student details by username/roll number
     * @param string $username - Student roll number
     * @return array - Student details or error
     */
    public function getStudentByUsername($username)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT s.id, u.username, u.name, u.email, u.mobile, u.status, u.role
                     FROM students s
                     JOIN users u ON s.username = u.username
                     WHERE u.username = ? AND u.role = 'student'"
                );
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $uusername, $name, $email, $mobile, $status, $role);
                        if ($stmt->fetch()) {
                            $res['data'] = [
                                'id' => $id,
                                'username' => $uusername,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'status' => $status,
                                'role' => $role
                            ];
                            $res['status'] = 1;
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getStudentByUsername - " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Get faculty details by username
     * @param string $username - Faculty username
     * @return array - Faculty details or error
     */
    public function getFacultyByUsername($username)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            if (!empty($this->conn)) {
                $stmt = $this->conn->prepare(
                    "SELECT f.id, u.username, u.name, u.email, u.mobile, f.designation, u.status, u.role
                     FROM faculties f
                     JOIN users u ON f.username = u.username
                     WHERE u.username = ? AND u.role = 'faculty'"
                );
                if ($stmt) {
                    $stmt->bind_param("s", $username);
                    if ($stmt->execute()) {
                        $stmt->bind_result($id, $uusername, $name, $email, $mobile, $designation, $status, $role);
                        if ($stmt->fetch()) {
                            $res['data'] = [
                                'id' => $id,
                                'username' => $uusername,
                                'name' => $name,
                                'email' => $email,
                                'mobile' => $mobile,
                                'designation' => $designation,
                                'status' => $status,
                                'role' => $role
                            ];
                            $res['status'] = 1;
                        }
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getFacultyByUsername - " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Reset student password (hashed to bcrypt of roll number)
     * @param string $username - Student roll number
     * @param string $remarks - Optional remarks for audit log
     * @return array - Status and result
     */
    public function resetStudentPwd($username, $remarks = "")
    {
        $myname = $this->classname . " - resetStudentPwd - ";
        $res = ['status' => 0];
        try {
            if (!empty($this->conn)) {
                // Hash password in PHP using bcrypt
                $new_password = password_hash($username, PASSWORD_BCRYPT);
                
                $stmt = $this->conn->prepare("UPDATE users SET password = ? WHERE username = ? AND role = 'student'");
                if ($stmt) {
                    $stmt->bind_param("ss", $new_password, $username);
                    if ($stmt->execute()) {
                        $res['status'] = 1;
                        
                        // Log activity with remarks
                        $details = "Student password reset for username: " . $username;
                        if (!empty($remarks)) {
                            $details .= " | Remarks: " . $remarks;
                        }
                        $this->dbActivityLog($_SESSION['userid'], "Password Reset", $details, "");
                    } else {
                        $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                    }
                    $stmt->close();
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    /**
     * Reset faculty password (hashed to bcrypt of mobile number)
     * @param int $faculty_id - Faculty ID
     * @param string $remarks - Optional remarks for audit log
     * @return array - Status and result
     */
    public function resetFacPwd($faculty_id, $remarks = "")
    {
        $myname = $this->classname . " - resetFacPwd - ";
        $res = ['status' => 0];
        try {
            if (!empty($this->conn)) {
                // Step 1: Fetch faculty mobile number
                $fetch_stmt = $this->conn->prepare(
                    "SELECT u.mobile, u.username FROM users u 
                     JOIN faculties f ON u.username = f.username 
                     WHERE f.id = ?"
                );
                if ($fetch_stmt) {
                    $fetch_stmt->bind_param("i", $faculty_id);
                    $fetch_stmt->execute();
                    $fetch_stmt->bind_result($mobile, $username);
                    if ($fetch_stmt->fetch()) {
                        $fetch_stmt->close();
                        
                        // Step 2: Hash password in PHP using bcrypt
                        $new_password = password_hash($mobile, PASSWORD_BCRYPT);
                        
                        // Step 3: Update with bcrypt hash
                        $stmt = $this->conn->prepare(
                            "UPDATE users u 
                             JOIN faculties f ON u.username = f.username
                             SET u.password = ? WHERE f.id = ?"
                        );
                        if ($stmt) {
                            $stmt->bind_param("si", $new_password, $faculty_id);
                            if ($stmt->execute()) {
                                $res['status'] = 1;
                                
                                // Log activity with remarks
                                $details = "Faculty password reset for username: " . $username;
                                if (!empty($remarks)) {
                                    $details .= " | Remarks: " . $remarks;
                                }
                                $this->dbActivityLog($_SESSION['userid'], "Password Reset", $details, "");
                            } else {
                                $this->logs->errLog($myname . "Statement execution failed: " . $this->conn->error);
                            }
                            $stmt->close();
                        }
                    } else {
                        $fetch_stmt->close();
                        $this->logs->errLog($myname . "Faculty not found: " . $faculty_id);
                    }
                }
            }
        } catch (Exception $e) {
            $this->logs->errLog($myname . "Exception: " . $e->getMessage());
        }
        return $res;
    }

    public function getAllBuildings()
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, building_name, status FROM buildings ORDER BY building_name");
            if ($stmt->execute()) {
                $stmt->bind_result($id, $building_name, $status);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'building_name' => $building_name, 'status' => $status];
                }
                $res['status'] = 1;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getAllBuildings - " . $e->getMessage());
        }
        return $res;
    }

    public function addBuilding($building_name)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("INSERT INTO buildings (building_name) VALUES (?)");
            $stmt->bind_param("s", $building_name);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Building added successfully";
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - addBuilding - " . $e->getMessage());
            $res['err'] = "Building already exists or database error";
        }
        return $res;
    }

    public function updateBuilding($id, $building_name, $status)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("UPDATE buildings SET building_name = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sii", $building_name, $status, $id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Building updated successfully";
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - updateBuilding - " . $e->getMessage());
            $res['err'] = "Failed to update building";
        }
        return $res;
    }

    public function deleteBuilding($id)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("DELETE FROM buildings WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Building deleted successfully";
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - deleteBuilding - " . $e->getMessage());
            $res['err'] = "Cannot delete building with associated halls";
        }
        return $res;
    }

    public function getHallsByBuilding($building_id)
    {
        $res = ['status' => 0, 'data' => []];
        try {
            $stmt = $this->conn->prepare("SELECT id, hall_name, status FROM halls WHERE building_id = ? ORDER BY hall_name");
            $stmt->bind_param("i", $building_id);
            if ($stmt->execute()) {
                $stmt->bind_result($id, $hall_name, $status);
                while ($stmt->fetch()) {
                    $res['data'][] = ['id' => $id, 'hall_name' => $hall_name, 'status' => $status];
                }
                $res['status'] = 1;
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - getHallsByBuilding - " . $e->getMessage());
        }
        return $res;
    }

    public function addHall($building_id, $hall_name)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("INSERT INTO halls (building_id, hall_name) VALUES (?, ?)");
            $stmt->bind_param("is", $building_id, $hall_name);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Hall added successfully";
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - addHall - " . $e->getMessage());
            $res['err'] = "Hall already exists or database error";
        }
        return $res;
    }

    public function updateHall($id, $hall_name, $status)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("UPDATE halls SET hall_name = ?, status = ? WHERE id = ?");
            $stmt->bind_param("sii", $hall_name, $status, $id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Hall updated successfully";
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - updateHall - " . $e->getMessage());
            $res['err'] = "Failed to update hall";
        }
        return $res;
    }

    public function deleteHall($id)
    {
        $res = ['status' => 0];
        try {
            $stmt = $this->conn->prepare("DELETE FROM halls WHERE id = ?");
            $stmt->bind_param("i", $id);
            if ($stmt->execute()) {
                $res['status'] = 1;
                $res['message'] = "Hall deleted successfully";
            }
            $stmt->close();
        } catch (Exception $e) {
            $this->logs->errLog($this->classname . " - deleteHall - " . $e->getMessage());
            $res['err'] = "Failed to delete hall";
        }
        return $res;
    }
}
