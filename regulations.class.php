<?php
require_once("user.class.php");

class Regulations extends User
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAllRegulations()
    {
        $res = ['status' => 0, 'data' => []];

        try {
            $stmt = $this->conn->prepare("SELECT r.id, r.regulation, r.prog_id, r.updatedat, p.program_code, p.prog_shortname FROM regulations r JOIN programs p ON r.prog_id = p.id ORDER BY p.prog_shortname ASC, r.regulation ASC");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllRegulations: " . $e->getMessage());
            $res['error'] = 'Failed to fetch regulations.';
        }

        return $res;
    }

    public function addOrUpdateRegulation(array $data)
    {
        $regulation = strtoupper(trim($data['regulation'] ?? ''));
        $prog_id = (int) ($data['prog_id'] ?? 0);

        if (!empty($data['id'])) {
            $stmt = $this->conn->prepare("UPDATE regulations SET regulation = ?, prog_id = ? WHERE id = ?");
            $stmt->bind_param("sii", $regulation, $prog_id, $data['id']);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO regulations (regulation, prog_id) VALUES (?, ?)");
            $stmt->bind_param("si", $regulation, $prog_id);
        }

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating regulation. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Regulation '$regulation' added/updated.");
            return ['status' => 1, 'message' => 'Regulation saved successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateRegulation: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function deleteRegulation(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM regulations WHERE id = ?");
        $stmt->bind_param("i", $id);

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete regulation. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Regulation ID $id deleted.");
            return ['status' => 1, 'message' => 'Regulation deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteRegulation: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }
}
