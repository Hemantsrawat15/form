<?php
// This file is now only responsible for the database connection.
// All CORS headers and content-type headers have been moved.

class Database {
    private $host = "localhost"; // Use "localhost" if you are not using Docker
    private $db_name = "drdo_db";
    private $username = "root";
    private $password = ""; // Use "" if not using Docker and no password is set

    public $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->db_name);
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
        } catch (Exception $e) {
            http_response_code(503);
            // Set content-type here because we are outputting JSON
            header('Content-Type: application/json');
            echo json_encode(["success" => false, "message" => "Database connection error."]);
            exit();
        }
        return $this->conn;
    }
}
?>