<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';

$email = isset($_GET['email']) ? $_GET['email'] : '';
$password = isset($_GET['password']) ? $_GET['password'] : '';

if (!$email) {
    echo json_encode(["message" => "Please provide email parameter"]);
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// 1. Check if user exists regardless of status
$query_raw = "SELECT * FROM id_emp WHERE email_id = ?";
$stmt = $conn->prepare($query_raw);
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

$debug_info = [
    "input_email" => $email,
    "user_found" => $user ? "YES" : "NO",
];

if ($user) {
    $debug_info['user_id'] = $user['id'];
    $debug_info['stored_email'] = $user['email_id'];
    $debug_info['stored_password_hash'] = substr($user['password'], 0, 20) . "..."; // Show partial hash
    $debug_info['is_deleted'] = $user['is_deleted'];
    $debug_info['status'] = $user['status'];
    
    // Verify password if provided
    if ($password) {
        $verify = password_verify($password, $user['password']);
        $debug_info['password_check'] = $verify ? "MATCH" : "MISMATCH";
        
        // Check if stored password is plain text (common mistake)
        if ($password === $user['password']) {
            $debug_info['WARNING'] = "Stored password is PLAIN TEXT! functionality requires Hash.";
        }
    }
}

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>
