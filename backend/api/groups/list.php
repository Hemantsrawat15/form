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

// --- THE FIX ---
// Changed WHERE clause to allow 'no', NULL, or empty string
$query = "SELECT g.id, g.name as group_name, g.fullname, 
          COUNT(f.id) as form_count
          FROM id_group g
          LEFT JOIN forms f ON g.id = f.group_id
          WHERE (g.is_deleted = 'no' OR g.is_deleted IS NULL OR g.is_deleted = '')
          GROUP BY g.id 
          ORDER BY g.name";

$result = $conn->query($query);
$groups = $result->fetch_all(MYSQLI_ASSOC);

$conn->close();

echo json_encode($groups);
?>