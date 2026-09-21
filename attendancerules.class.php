<?php
require_once("user.class.php");

class AttendanceRules extends User
{
    public function __construct()
    {
        parent::__construct();
    }

    public function getAllAttendanceRules()
    {
        $res = ['status' => 0, 'data' => []];

        try {
            $stmt = $this->conn->prepare("SELECT ar.id, ar.reg_id, ar.criteria, ar.operator1, ar.value1, ar.operator2, ar.value2, ar.updatedat, r.regulation, p.prog_shortname, p.program_code FROM attendance_rules ar JOIN regulations r ON ar.reg_id = r.id JOIN programs p ON r.prog_id = p.id ORDER BY p.prog_shortname ASC, r.regulation ASC, ar.criteria ASC, ar.value1 DESC, ar.value2 DESC");
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAllAttendanceRules: " . $e->getMessage());
            $res['error'] = 'Failed to fetch attendance rules.';
        }

        return $res;
    }

    public function getAttendanceRulesByRegulationId(int $reg_id)
    {
        $res = ['status' => 0, 'data' => []];

        try {
            $stmt = $this->conn->prepare("SELECT id, reg_id, criteria, operator1, value1, operator2, value2 FROM attendance_rules WHERE reg_id = ? ORDER BY criteria ASC, value1 DESC, value2 DESC, id ASC");
            $stmt->bind_param("i", $reg_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $res['data'] = $result->fetch_all(MYSQLI_ASSOC);
            $res['status'] = 1;
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in getAttendanceRulesByRegulationId: " . $e->getMessage());
            $res['error'] = 'Failed to fetch attendance rules by regulation.';
        }

        return $res;
    }

    public function groupRulesByCriteria(array $rules)
    {
        $grouped = [];
        foreach ($rules as $rule) {
            $criteria = $rule['criteria'] ?? '';
            if (!isset($grouped[$criteria])) {
                $grouped[$criteria] = [];
            }
            $grouped[$criteria][] = $rule;
        }
        return $grouped;
    }

    public function evaluateRule(float $percentage, array $rule): bool
    {
        return $this->compare($percentage, $rule['operator1'] ?? '', (float) ($rule['value1'] ?? 0))
            && $this->compare($percentage, $rule['operator2'] ?? '', (float) ($rule['value2'] ?? 0));
    }

    public function getMatchedRules(float $percentage, array $rules): array
    {
        $matched = [];
        foreach ($rules as $rule) {
            if ($this->evaluateRule($percentage, $rule)) {
                $matched[] = $rule;
            }
        }
        return $matched;
    }

    public function formatRuleLabel(array $rule): string
    {
        $parts = [];

        $value1 = (float) ($rule['value1'] ?? 0);
        $value2 = (float) ($rule['value2'] ?? 0);

        if ($value1 != 0) {
            $parts[] = trim(($rule['operator1'] ?? '') . ' ' . $rule['value1']);
        }

        if ($value2 != 0) {
            $parts[] = trim(($rule['operator2'] ?? '') . ' ' . $rule['value2']);
        }

        return implode(' and ', $parts);
    }

    private function compare(float $actual, string $operator, float $expected): bool
    {
        switch ($operator) {
            case '<':
                return $actual < $expected;
            case '<=':
                return $actual <= $expected;
            case '>':
                return $actual > $expected;
            case '>=':
                return $actual >= $expected;
            case '=':
            case '==':
                return $actual == $expected;
            case '!=':
                return $actual != $expected;
            default:
                return false;
        }
    }

    public function addOrUpdateAttendanceRule(array $data)
    {
        $reg_id = (int) ($data['reg_id'] ?? 0);
        $criteria = trim($data['criteria'] ?? '');
        $operator1 = trim($data['operator1'] ?? '');
        $value1 = (int) ($data['value1'] ?? 0);
        $operator2 = trim($data['operator2'] ?? '');
        $value2 = (int) ($data['value2'] ?? 0);

        if (!empty($data['id'])) {
            $stmt = $this->conn->prepare("UPDATE attendance_rules SET reg_id = ?, criteria = ?, operator1 = ?, value1 = ?, operator2 = ?, value2 = ? WHERE id = ?");
            $stmt->bind_param("ississi", $reg_id, $criteria, $operator1, $value1, $operator2, $value2, $data['id']);
        } else {
            $stmt = $this->conn->prepare("INSERT INTO attendance_rules (reg_id, criteria, operator1, value1, operator2, value2) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issisi", $reg_id, $criteria, $operator1, $value1, $operator2, $value2);
        }

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to execute query for adding/updating attendance rule. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Attendance rule for reg_id '$reg_id' added/updated.");
            return ['status' => 1, 'message' => 'Attendance rule saved successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in addOrUpdateAttendanceRule: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }

    public function deleteAttendanceRule(int $id)
    {
        $stmt = $this->conn->prepare("DELETE FROM attendance_rules WHERE id = ?");
        $stmt->bind_param("i", $id);

        try {
            if (!$stmt->execute()) {
                throw new Exception("Failed to delete attendance rule. Error: " . $stmt->error);
            }

            $this->logs->activityLog("Attendance rule ID $id deleted.");
            return ['status' => 1, 'message' => 'Attendance rule deleted successfully.'];
        } catch (Exception $e) {
            $this->logs->errLog("Exception occurred in deleteAttendanceRule: " . $e->getMessage());
            return ['status' => 0, 'error' => $e->getMessage()];
        }
    }
}
