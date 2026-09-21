<?php
require_once("logs.class.php");

class DBCredentials
{
	private static $instance = null;
	protected $conn;
	protected $logs;

	// Private constructor to prevent direct object creation
	public function __construct()
	{
		// Load environment variables first
		if (!defined('DB_HOST')) {
			require_once dirname(__DIR__) . '/.env.php';
		}
		
		$this->logs = new Logs();

		// Fetch DB credentials from environment variables
		$this->conn = new mysqli($this->getHost(), $this->getDBUser(), $this->getDBPwd(), $this->getDBName());

		if ($this->conn->connect_error) {
			$this->logs->errLog("DB connection failed: " . $this->conn->connect_error);
			//die("Database connection failed.");
		}
	}

	// Singleton instance of the database connection
	public static function getInstance()
	{
		if (self::$instance == null) {
			self::$instance = new DBCredentials();
		}
		return self::$instance;
	}

	// Return the active DB connection
	public function getConnection()
	{
		return $this->conn;
	}

	// Get host from environment variable
	protected function getHost()
	{
		return DB_HOST;
	}

	// Get DB username from environment variable
	protected function getDBUser()
	{
		return DB_USER;
	}

	// Get DB password from environment variable
	protected function getDBPwd()
	{
		return DB_PASS;
	}

	// Get DB name from environment variable
	protected function getDBName()
	{
		return DB_NAME;
	}

	// Optional: Function to log actions to the database (for audit purposes)
	public function dbActivityLog($userId, $action, $details, $role = "")
	{
		try {
			// Fetch DB credentials from environment variables
			$conn = new mysqli($this->getHost(), $this->getDBUser(), $this->getDBPwd(), $this->getDBName());
			if(empty($role)){
				$sql = "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)";
			}else{
				if($role=="Faculty"){
					$sql = "INSERT INTO fac_activity_logs (user_id, action, details) VALUES (?, ?, ?)";
				}
			}

			if(!empty($sql)){
				$stmt = $conn->prepare($sql);
				$stmt->bind_param("iss", $userId, $action, $details);
				if (!$stmt->execute()) {
					$this->logs->errLog("Failed to log activity: " . $stmt->error);
				}
				$stmt->close();
			}
		} catch (Exception $e) {
		}
	}

	// Close DB connection when the instance is destroyed
	public function __destruct()
	{
		if ($this->conn) {
			$this->conn->close();
		}
	}
}
