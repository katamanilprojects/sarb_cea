<?php
require_once("dbcredentials.class.php");

class Programs extends DBCredentials
{
    private $classname = "Programs";

    public function __construct()
    {
        parent::__construct();
    }

        public function getAllPrograms()
    {
        $res = ['status' => 0];

        try {
            $stmt = $this->conn->prepare("SELECT id, program_code, prog_shortname, prog_fullname FROM programs");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllPrograms: " . $e->getMessage());
            $res['err'] = "Failed to fetch programs.";
        }

        return $res;
    }

    // Function to add or update a program
    public function addOrUpdateProgram(array $data)
    {
        $program_code = $data['program_code'];
        $prog_shortname = $data['prog_shortname'];
        $prog_fullname = $data['prog_fullname'];

        if (!empty($data["id"])) {
            $stmt = $this->conn->prepare("UPDATE programs SET program_code = ?, prog_shortname = ?, prog_fullname = ? WHERE id = ?");
            $stmt->bind_param("sssi", $program_code, $prog_shortname, $prog_fullname, $data["id"]);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO programs (program_code, prog_shortname, prog_fullname) VALUES (?, ?, ?)");
            $stmt->bind_param("sss", $program_code, $prog_shortname, $prog_fullname);
        }

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating program. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Program '$prog_fullname' added/updated.");
            return ['status' => 1, 'message' => 'Program saved successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateProgram: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    // Function to delete a program
    public function deleteProgram(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM programs WHERE id = ?");
        $stmt->bind_param("i", $id);

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete program. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Program ID $id deleted.");
            return ['status' => 1, 'message' => 'Program deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteProgram: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }
}