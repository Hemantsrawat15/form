<?php
require_once __DIR__ . '/../cors.php';
header('Content-Type: application/json; charset=UTF-8');

require_once '../config/database.php';
require_once '../config/jwt_handler.php';

$jwt_handler = new JwtHandler();
$user_id = $jwt_handler->getUserId();

if (!$user_id) {
    http_response_code(401);
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit();
}

$db = new Database();
$conn = $db->getConnection();
// Make sure you have added a user_id column to your groups table
$query = "SELECT g.id, g.group_name, g.description, COUNT(f.id) as form_count 
          FROM `groups` g 
          LEFT JOIN forms f ON g.id = f.group_id 
          WHERE g.user_id = ? 
          GROUP BY g.id ORDER BY g.group_name";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$groups = $result->fetch_all(MYSQLI_ASSOC);
$conn->close();

echo json_encode($groups);
?>