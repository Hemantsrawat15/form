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

// Modified query to show ALL groups with creator information
$query = "SELECT g.id, g.group_name, g.description, g.created_by, 
          u.username as creator_username, u.full_name as creator_name,
          COUNT(f.id) as form_count
          FROM groups g
          LEFT JOIN users u ON g.created_by = u.id
          LEFT JOIN forms f ON g.id = f.group_id
          GROUP BY g.id 
          ORDER BY g.group_name";

$result = $conn->query($query);
$groups = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode($groups);
?>
