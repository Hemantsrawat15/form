<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');
require_once '../config/database.php';

$db = new Database();
$conn = $db->getConnection();

// 1. Check if table exists and show columns
$columns = [];
$result = $conn->query("SHOW COLUMNS FROM id_emp");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $columns[] = $row['Field'] . " (" . $row['Type'] . ")";
    }
}

// 2. Fetch all users (limit 10)
$query = "SELECT id, first_name, email_id, user_name, status, is_deleted, password FROM id_emp LIMIT 10";
$result = $conn->query($query);
$users = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        // Truncate password for display
        $row['password'] = substr($row['password'], 0, 10) . "..."; 
        $users[] = $row;
    }
}

echo json_encode([
    "debug_message" => "Here is what is actually inside your database:",
    "table_structure" => $columns,
    "registered_users" => $users
], JSON_PRETTY_PRINT);
?>