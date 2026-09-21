<?php

class Logs{
    
    // Function to log error messages
    public function errLog($message) {
        $this->writeLog($message, "ERROR");
    }

    // Function to log activity messages (e.g., successful inserts, logins)
    public function activityLog($message) {
        $this->writeLog($message, "ACTIVITY");
    }

    // Function to log warning messages
    public function warningLog($message) {
        $this->writeLog($message, "WARNING");
    }

    // Private function to write logs to a file
    private function writeLog($message, $type) {
        // Log file path: Create a new log file each day
        $filePath = 'logs/' . date('Y-m-d') . '.log';

        // Log entry format
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [$type] $message" . PHP_EOL;

        // Write log entry to file (append mode)
        file_put_contents($filePath, $logEntry, FILE_APPEND);
    }

    // Function to rotate old logs (optional, depending on how long you want to keep logs)
    public function rotateLogs() {
        $files = glob('logs/*.log');
        foreach ($files as $file) {
            if (filemtime($file) < strtotime('-30 days')) {
                rename($file, 'logs/archive/' . basename($file)); // Archive older logs
            }
        }
    }
}
?>